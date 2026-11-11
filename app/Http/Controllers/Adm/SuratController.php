<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Adm\ApprovedDocument;
use App\Models\Adm\ListSurat;
use App\Models\Adm\PenomoranSurat;
use App\Models\Adm\Surat;
use App\Models\Adm\SuratTtdBasah;
use App\Models\Adm\SuratVoid;
use App\Services\SuratLogService;
use App\Services\SuratTtdBasahService;
use Illuminate\Support\Facades\DB;
use App\Services\SuratTteService;
use App\Models\Employe;
use App\Models\ESign\VerifStep;
use App\Models\Structure;
use App\Models\Verify\ListVerif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\SignatureService;
use App\Services\CryptoService;

class SuratController extends Controller
{
    function insert(Request $request)
    {


        $surat = new Surat();


        if ($request->lampiran + 0 > 0) {
            $file = base64_decode(str_replace('data:application/pdf;base64,', '', $request->fileLampiran), true);
            $fileName = 'lampiran/' . date('Y') . '/' . sprintf("%02d", ((PenomoranSurat::where('type', $request->type)->first()->last_number) + 1)) . '.pdf';
            if (Storage::disk('public_adm')->put($fileName, $file)) {
                $surat->file_lampiran = $fileName;
            }
        }

        $surat->nomor_surat = sprintf("%02d", ((PenomoranSurat::where('type', $request->type)->first()->last_number) + 1)) . '/' . PenomoranSurat::where('type', $request->type)->first()->kode . '/' . $this->getRomawi(date('m')) . '/' . date('Y');
        $surat->no_document = unique_random('documents', 'doc_id', 40);
        $surat->kepada = $request->kepada;
        $surat->perihal = $request->perihal;
        $surat->j_lampiran = $request->lampiran;
        $surat->jenis_lampiran = $request->jenislampiran;
        $surat->isi_surat = $request->isiSurat;
        $surat->tembusans = implode(",", $request->tembusans);
        $surat->id_divisi = $request->divisi;
        $surat->submitted_by = Employe::employeId();
        $surat->submitted_current_position = (Structure::where('employe_id', $surat->submitted_by)->first('position_name')->position_name);
        $surat->sign_by = $request->ttdBy;
        $surat->bahasa = $request->bhs;

        if ($request->boolean('ttdBasah')) {
            // surat tanda tangan basah: tanpa alur paraf di sistem (lihat SuratTtdBasahService)
            DB::connection('adm')->transaction(function () use ($surat) {
                $batas = SuratTtdBasahService::batasNotifikasi();
                $surat->save();
                SuratTtdBasahService::tandai($surat, $batas);
            });

            return new PostResource(true, 'Data Inserted', ['id' => $surat->id, 'nomor_surat' => $surat->nomor_surat]);
        }

        if ($surat->save()) {
            return new PostResource(true, 'Data Inserted', []);
        }

    }

    // Tandai surat void: surat tidak terpakai lagi dan belum ditandatangani (belum approved penuh). Hanya pembuat surat (atau PICAdm) yang boleh.
    public function void(Request $request, $id)
    {
        $surat = Surat::find($id);
        if (!$surat) {
            return response()->json(['success' => false, 'message' => 'Surat tidak ditemukan.'], 404);
        }
        $me = Employe::employeId();
        $dokumen = \DB::connection('esign')->table('documents')->where('doc_id', $surat->no_document)->first();
        if (($dokumen->created_by ?? null) !== $me && !in_array('PICAdm', auth()->user()->roles ?? [], true)) {
            return response()->json(['success' => false, 'message' => 'Hanya pembuat surat yang bisa menandai void.'], 403);
        }
        if (in_array((int) $surat->id, SuratVoid::ids(), true)) {
            return response()->json(['success' => false, 'message' => 'Surat ini sudah ditandai void.'], 422);
        }
        // surat TTD manual: boleh di-void selama berkas ber-ttd belum diunggah; sesudahnya surat dianggap sudah ditandatangani
        if (SuratTtdBasah::peta()[(int) $surat->id] ?? false) {
            return response()->json(['success' => false, 'message' => 'Surat TTD manual yang sudah diunggah berkas ber-ttd tidak bisa di-void.'], 422);
        }
        // boleh selama belum final: paraf sebagian atau penolakan tidak menghalangi; yang sudah ditandatangani tidak bisa di-void
        if (VerifStep::where('id_doc', $surat->no_document)->where('type', 'sign')->where('status', 'approve')->exists()) {
            return response()->json(['success' => false, 'message' => 'Surat sudah ditandatangani (approved), tidak bisa di-void.'], 422);
        }

        SuratVoid::create(['surat_id' => $surat->id, 'nomor_surat' => $surat->nomor_surat, 'voided_by' => $me, 'alasan' => (string) $request->alasan]);
        SuratLogService::catat($surat->no_document, $me, 'Menandai surat void (nomor ' . $surat->nomor_surat . ' tidak digunakan lagi)' . (trim((string) $request->alasan) !== '' ? ': ' . trim((string) $request->alasan) : ''));

        return new PostResource(true, 'Surat ' . $surat->nomor_surat . ' ditandai void; nomornya bisa dipakai untuk backdate.', []);
    }

    // Akses ke berkas ber-ttd: pembuat surat, penandatangan surat, PICAdm, SuperAdminAdm, atau Director (yang membuka Surat Keluar)
    private function bolehTtdBasah(Surat $surat): bool
    {
        $roles = auth()->user()->roles ?? [];
        $dokumen = DB::connection('esign')->table('documents')->where('doc_id', $surat->no_document)->first();
        $me = Employe::employeId();

        return ($dokumen->created_by ?? null) === $me || (string) $surat->sign_by === (string) $me || array_intersect(['PICAdm', 'SuperAdminAdm', 'Director'], $roles) !== [];
    }

    // Unggah surat yang sudah ditandatangani basah (PDF, JPG, atau PNG, maksimal 10 MB); unggahan baru menggantikan yang lama
    public function uploadTtdBasah(Request $request, $id)
    {
        $surat = Surat::find($id);
        $baris = $surat ? SuratTtdBasah::where('surat_id', $surat->id)->first() : null;
        if (!$baris) {
            return response()->json(['success' => false, 'message' => 'Surat ini bukan surat tanda tangan basah.'], 422);
        }
        if (!$this->bolehTtdBasah($surat)) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak mengunggah berkas surat ini.'], 403);
        }
        $isi = base64_decode(preg_replace('#^data:[^;]*;base64,#', '', (string) $request->file), true);
        if ($isi === false || $isi === '' || strlen($isi) > 10 * 1024 * 1024) {
            return response()->json(['success' => false, 'message' => 'Berkas tidak valid atau lebih dari 10 MB.'], 422);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($isi);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if (!$ext) {
            return response()->json(['success' => false, 'message' => 'Berkas harus PDF, JPG, atau PNG.'], 422);
        }

        $nama = 'ttd_basah/' . date('Y') . '/' . $surat->id . '-' . \Illuminate\Support\Str::random(16) . '.' . $ext;
        Storage::disk('public_adm')->put($nama, $isi);
        $lama = $baris->file_ttd;
        $baris->update(['file_ttd' => $nama, 'mime' => $mime, 'uploaded_by' => Employe::employeId()]);
        $rincian = ' (' . strtoupper($ext) . ', ' . number_format(strlen($isi) / 1024, 0, ',', '.') . ' KB)';
        SuratLogService::catat($surat->no_document, Employe::employeId(), ($lama ? 'Mengganti berkas surat bertanda tangan manual' : 'Mengunggah berkas surat bertanda tangan manual') . $rincian);
        if ($lama && $lama !== $nama) {
            Storage::disk('public_adm')->delete($lama);
        }

        return new PostResource(true, 'Berkas surat ber-ttd tersimpan', []);
    }

    public function unduhTtdBasah($id)
    {
        $surat = Surat::find($id);
        $baris = $surat ? SuratTtdBasah::where('surat_id', $surat->id)->first() : null;
        if (!$baris || !$baris->file_ttd || !Storage::disk('public_adm')->exists($baris->file_ttd)) {
            return response()->json(['success' => false, 'message' => 'Belum ada berkas surat ber-ttd.'], 404);
        }
        if (!$this->bolehTtdBasah($surat)) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak membuka berkas surat ini.'], 403);
        }

        return new PostResource(true, 'berkas', ['file' => base64_encode(Storage::disk('public_adm')->get($baris->file_ttd)), 'mime' => $baris->mime]);
    }

    public function update(Request $request)
    {
        $surat = Surat::find($request->id);
        if ($surat && in_array((int) $surat->id, SuratVoid::ids(), true)) {
            return response()->json(['success' => false, 'message' => 'Surat ini sudah di-void.'], 422);
        }
        if ($surat && (SuratTtdBasah::peta()[(int) $surat->id] ?? false)) {
            return response()->json(['success' => false, 'message' => 'Surat TTD manual yang sudah diunggah berkas ber-ttd tidak bisa diubah.'], 422);
        }
        if ($request->lampiran + 0 > 0 && $surat->file_lampiran !== '') {
            $file = base64_decode(str_replace('data:application/pdf;base64,', '', $request->fileLampiran), true);
            $fileName = 'lampiran/' . date('Y') . '/' . strtok($surat->nomor_surat, '/') . '.pdf';
            if (Storage::disk('public_adm')->put($fileName, $file)) {
                $surat->file_lampiran = $fileName;
            }
        }

        $surat->kepada = $request->kepada;
        $surat->perihal = $request->perihal;
        $surat->j_lampiran = $request->lampiran;
        $surat->jenis_lampiran = $request->jenislampiran;
        $surat->isi_surat = $request->isiSurat;
        $surat->tembusans = implode(",", $request->tembusans);
        $surat->id_divisi = $request->divisi;
        $surat->submitted_by = Employe::employeId();
        $surat->submitted_current_position = (Structure::where('employe_id', $surat->submitted_by)->first('position_name')->position_name);
        $surat->sign_by = $request->ttdBy;
        $surat->bahasa = $request->bhs;
        // surat ttd basah: trigger update membuat ulang langkah paraf dan notifikasi review; notifikasinya dibatalkan lagi
        $basah = array_key_exists((int) $surat->id, SuratTtdBasah::peta());
        $batas = $basah ? SuratTtdBasahService::batasNotifikasi() : 0;
        if ($surat->save()) {
            if ($basah) {
                SuratTtdBasahService::hapusNotifikasi($surat, $batas);
            }
            // isi surat berubah: PDF bertanda tangan yang tersimpan sebelumnya sudah tidak berlaku
            SuratTteService::forgetStoredPdfs($surat->no_document);

            return new PostResource(true, 'Data Updated', []);
        }
    }

    // Surat ttd basah yang berkas ber-ttd-nya sudah diunggah dianggap sudah ditandatangani penandatangannya: tampil seperti baris
    // approved_document (type sign) di Surat Keluar dan di daftar surat yang sudah ditandatangani penandatangan ($signer = id penandatangan).
    private function barisTtdBasah(?string $signer = null)
    {
        $void = array_flip(SuratVoid::ids());
        $ids = array_values(array_filter(array_keys(array_filter(SuratTtdBasah::peta())), fn ($id) => !isset($void[$id])));
        if (!$ids) {
            return collect();
        }
        $q = ListSurat::whereIn('id', $ids);
        if ($signer !== null) {
            $q->where('sign_by', $signer);
        }
        $surat = $q->get();
        $unggah = SuratTtdBasah::whereIn('surat_id', $ids)->pluck('updated_at', 'surat_id');
        $orang = Structure::whereIn('employe_id', $surat->pluck('sign_by')->unique())->get(['employe_id', 'first_name', 'position_name'])->keyBy('employe_id');

        return $surat->each(function ($s) use ($unggah, $orang) {
            $s->unique_id = 'basah-' . $s->id;
            $s->type = 'sign';
            $s->id_employe = $s->sign_by;
            $s->status = 'signed';
            $s->updated_at = $unggah[$s->id] ?? $s->created_at;
            $s->reviewer = $orang[$s->sign_by]->first_name ?? null;
            $s->position_reviewer = $orang[$s->sign_by]->position_name ?? null;
            $s->ttd_basah = true;
            $s->file_ttd = true;
        });
    }

    public function getSurat($what)
    {
        if ($what === 'approved') {
            $data = ApprovedDocument::where('id_employe', Employe::employeId())->get()
                ->concat($this->barisTtdBasah(Employe::employeId()))->sortByDesc('updated_at')->values();
        } elseif ($what === 'review') {
            // surat void tidak lagi perlu direview
            $data = ListSurat::where('current_reviewer', Employe::employeId())->whereNotIn('id', array_merge(SuratVoid::ids(), array_keys(SuratTtdBasah::peta())))->latest()->get();
        } elseif ($what === 'signed') {
            $data = ApprovedDocument::where('type', 'sign')->latest('created_at')->get()
                ->concat($this->barisTtdBasah())->sortByDesc('created_at')->values();
        } else {
            $data = ListSurat::where('created_by', Employe::employeId())->latest()->get();
            $void = array_flip(SuratVoid::ids());
            $basah = SuratTtdBasah::peta();
            $data->each(function ($s) use ($void, $basah) {
                $s->void = isset($void[(int) $s->id]);
                $s->ttd_basah = array_key_exists((int) $s->id, $basah);
                $s->file_ttd = $basah[(int) $s->id] ?? false;
            });
        }

        // penanda jenis surat di setiap daftar: backdate, TTD manual, atau normal (tidak keduanya)
        $backdate = array_flip(SuratBackdate::suratIds());
        $basah = SuratTtdBasah::peta();
        $data->each(function ($s) use ($backdate, $basah) {
            $s->backdate = isset($backdate[(int) $s->id]);
            $s->ttd_basah = array_key_exists((int) $s->id, $basah);
        });

        return new PostResource(true, 'data surat', $data);
    }

    function detail($id)
    {
        // $doc = Surat::find($id);
        // $signer = ListVerif::where('id_doc', $doc->no_document)->where('type', 'sign')->first();

        // $document['perubahan_terakhir'] = $doc->updated_at;
        // $document['nomor_dokument'] = $doc->no_document;

        // $document['nomorSurat'] = $doc->nomor_surat;
        // $document['tglSurat'] = $doc->created_at;
        // $document['lampiran'] = $doc->j_lampiran;
        // $document['jenisLampiran'] = $doc->jenis_lampiran;
        // $document['kepada'] = $doc->kepada;
        // $document['perihal'] = $doc->perihal;
        // $document['isiSurat'] = $doc->isi_surat;
        // $document['ttdBy'] = $doc->sign_by;
        // $document['bhs'] = $doc->bahasa;
        // if ($doc->tembusans !== null && $doc->tembusans !== '') {
        //     $document['tembusans'] = explode(',', $doc->tembusans);
        // } else {
        //     $document['tembusans'] = [];
        // }
        // if ($doc->file_lampiran !== null && file_exists(public_path('adm/' . $doc->file_lampiran))) {
        //     $document['lampiran'] = base64_encode(file_get_contents(public_path('adm/' . $doc->file_lampiran)));
        // } else {
        //     $document['lampiran'] = '-';
        // }
        // $document['signer']['first_name'] = $signer->employe_name;
        // $document['signer']['position_name'] = $signer->id_current_position;
        // $document['status']=ListSurat::find($id)->status;

        $d = ListSurat::find($id);
        $si = ListVerif::where('id_doc', $d->no_document)->where('type', 'sign')->first();

        // $data['signer']['first_name'] = $si->employe_name;
        $data['signer']['position_name'] = $si->id_current_position;
        $data['signer']['first_name'] = $si->employe_name;
        $data['perihal'] = $d->perihal;
        $data['kepada'] = $d->kepada;
        $data['divisi']=$d->divisi;
        $data['status']=$d->status;
        $data['id']=$d->id;

        // $data['signer'] = Employe::where('employe_id', $data->sign_by)->first();
        $data['tglSurat'] = $d->created_at;
        $data['nomorSurat'] = $d->nomor_surat;
        $data['lampiran'] = $d->j_lampiran;
        $data['jenisLampiran'] = $d->jenis_lampiran;
        $data['isiSurat'] = $d->isi_surat;
        $data['ttdBy'] = $d->sign_by;
        $data['bhs'] = $d->bhs;
        $data['tteCode'] = SuratTteService::codeFor($d->no_document);
        $basah = SuratTtdBasah::peta();
        $data['ttdBasah'] = array_key_exists((int) $d->id, $basah);
        $data['fileTtd'] = $basah[(int) $d->id] ?? false;
        if ($d->tembusans !== null && $d->tembusans !== '') {
            $data['tembusans'] = explode(',', $d->tembusans);
        } else {
            $data['tembusans'] = [];
        }

        if ($d->file_lampiran !== null && file_exists(public_path('adm/' . $d->file_lampiran))) {
            $data['fileLampiran'] = base64_encode(file_get_contents(public_path('adm/' . $d->file_lampiran)));
        } else {
            $data['fileLampiran'] = '';
        }
        return new PostResource(true, 'data surat', $data);
    }

    function detail1($id)
    {
        $doc = Surat::find($id);
        $signer = ListVerif::where('id_doc', $doc->no_document)->where('type', 'sign')->first();

        $document['perubahan_terakhir'] = $doc->updated_at;
        $document['nomor_dokument'] = $doc->no_document;

        $document['nomorSurat'] = $doc->nomor_surat;
        $document['tglSurat'] = $doc->created_at;
        $document['lampiran'] = $doc->j_lampiran;
        $document['jenisLampiran'] = $doc->jenis_lampiran;
        $document['kepada'] = $doc->kepada;
        $document['perihal'] = $doc->perihal;
        $document['isiSurat'] = $doc->isi_surat;
        $document['ttdBy'] = $doc->sign_by;
        $document['bhs'] = $doc->bahasa;
        if ($doc->tembusans !== null && $doc->tembusans !== '') {
            $document['tembusans'] = explode(',', $doc->tembusans);
        } else {
            $document['tembusans'] = [];
        }
        if ($doc->file_lampiran !== null && file_exists(public_path('adm/' . $doc->file_lampiran))) {
            $document['lampiran'] = base64_encode(file_get_contents(public_path('adm/' . $doc->file_lampiran)));
        } else {
            $document['lampiran'] = '-';
        }
        $document['signer']['first_name'] = $signer->employe_name;
        $document['signer']['position_name'] = $signer->id_current_position;
        $document['status']=ListSurat::find($id)->status;
        return new PostResource(true, 'data surat', $document);
    }

    function reviewDokumen($id_doc, Request $request)
    {
        $suratId = Surat::where('no_document', $id_doc)->first('id')->id;
        if (in_array((int) $suratId, SuratVoid::ids(), true)) {
            return response()->json(['success' => false, 'message' => 'Surat ini sudah di-void.'], 422);
        }
        if (array_key_exists((int) $suratId, SuratTtdBasah::peta())) {
            return response()->json(['success' => false, 'message' => 'Surat ini ditandatangani basah, tidak diproses di sistem.'], 422);
        }
        $document=self::detail1($suratId);
        // $doc = Surat::where('no_document', $id_doc)->first();
        // $signer = ListVerif::where('id_doc', $id_doc)->where('type', 'sign')->first();

        // $document['perubahan_terakhir'] = $doc->updated_at;
        // $document['nomor_dokument'] = $doc->no_document;

        // $document['nomorSurat'] = $doc->nomor_surat;
        // $document['tglSurat'] = $doc->created_at;
        // $document['lampiran'] = $doc->j_lampiran;
        // $document['jenisLampiran'] = $doc->jenis_lampiran;
        // $document['kepada'] = $doc->kepada;
        // $document['perihal'] = $doc->perihal;
        // $document['isiSurat'] = $doc->isi_surat;
        // $document['bhs'] = $doc->bahasa;
        // if ($doc->tembusans !== null && $doc->tembusans !== '') {
        //     $document['tembusans'] = explode(',', $doc->tembusans);
        // } else {
        //     $document['tembusans'] = [];
        // }
        // if ($doc->file_lampiran !== null && file_exists(public_path('adm/' . $doc->file_lampiran))) {
        //     $document['lampiran'] = base64_encode(file_get_contents(public_path('adm/' . $doc->file_lampiran)));
        // } else {
        //     $document['lampiran'] = '-';
        // }
        // $document['signer']['first_name'] = $signer->employe_name;
        // $document['signer']['position_name'] = $signer->id_current_position;

        CryptoService::ensureKeys(Employe::employeId());

        $signature = SignatureService::signDocument(Employe::employeId(), $document->toArray(request())['data']);

        $verif = VerifStep::where('id_doc', $id_doc)->where('id_employe', Employe::employeId())->where('status', NULL)->first();
        $verif->status = $request->status;
        $verif->ket = $request->catatan_persetujuan;
        $verif->signature=$signature;
        if ($verif->save()) {
            if ($request->status === 'approve') {
                SuratTteService::recordChain($verif, SuratTteService::contentHash($id_doc));
            }
            return new PostResource(true, 'success', $document);
        }

        return new PostResource(true, 'success',  $document->toArray(request())['data']);
    }

    function signPdf($id, Request $request)
    {
        $request->validate(['pdf' => 'required|string|max:30000000']);

        $doc = ListSurat::find($id);
        $tte = $doc ? SuratTteService::find($doc->no_document) : null;
        if (!$tte) {
            return response()->json(['success' => false, 'message' => 'Surat ini tidak memiliki TTE'], 404);
        }

        $bytes = base64_decode(preg_replace('#^data:application/pdf;base64,#', '', $request->pdf), true);
        if ($bytes === false || strncmp($bytes, '%PDF-', 5) !== 0) {
            return response()->json(['success' => false, 'message' => 'File PDF tidak valid'], 422);
        }

        try {
            $signed = SuratTteService::issueSignedPdf($bytes, $doc->no_document, $tte);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'PDF tidak dapat ditandatangani'], 500);
        }

        return new PostResource(true, 'success', ['pdf' => base64_encode($signed)]);
    }

    function readSurat($id_doc)
    {
        $doc = VerifStep::where('id_doc', $id_doc)->where('id_employe', Employe::employeId())->first();
        $doc->baca = 1;
        if ($doc->save()) {
            return new PostResource(true, 'success', []);
        }
    }

    function getRomawi($bln)
    {
        switch ($bln) {
            case 1:
                return "I";
            case 2:
                return "II";
            case 3:
                return "III";
            case 4:
                return "IV";
            case 5:
                return "V";
            case 6:
                return "VI";
            case 7:
                return "VII";
            case 8:
                return "VIII";
            case 9:
                return "IX";
            case 10:
                return "X";
            case 11:
                return "XI";
            case 12:
                return "XII";
            default:
                return "Invalid";
        }
    }
}
