<?php

namespace App\Http\Controllers\Docusign;

use App\Http\Controllers\Controller;
use App\Models\Docusign\DocusignDocument;
use App\Models\Docusign\DocusignSigner;
use App\Services\DocusignPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocusignController extends Controller
{
    private function disk()
    {
        return Storage::disk('local');
    }

    private function frontendUrl(): string
    {
        return rtrim(env('FRONTEND_URL', 'https://sys.pema.co.id'), '/');
    }

    private function sendSignRequest(DocusignDocument $doc, DocusignSigner $signer): void
    {
        $link = $this->frontendUrl() . '/docusign/sign/' . $signer->token;
        $title = e($doc->title);
        $name = e($signer->name);
        $html = "<p>Yth. {$name},</p>"
            . "<p>Anda diminta untuk menandatangani dokumen <b>{$title}</b>.</p>"
            . "<p>Klik tombol berikut untuk membuka dokumen dan menandatangani dengan satu klik:</p>"
            . "<p><a href=\"{$link}\" style=\"background:#1976d2;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px\">Tinjau &amp; Tanda Tangan</a></p>"
            . "<p>Jika tombol tidak berfungsi, salin tautan ini: {$link}</p>"
            . "<p>Tautan ini bersifat pribadi, jangan diteruskan kepada orang lain.</p>";

        Mail::html($html, function ($m) use ($signer, $doc) {
            $m->to($signer->email, $signer->name)->subject('Permintaan Tanda Tangan: ' . $doc->title);
        });
        $signer->update(['notified_at' => now()]);
    }

    private function canSign(DocusignDocument $doc, DocusignSigner $signer): bool
    {
        return !$doc->signers->contains(fn ($s) => $s->sign_order < $signer->sign_order && $s->status !== 'signed');
    }

    // Email every pending signer whose turn has come and who has not been notified yet.
    // Sent after the HTTP response so the user never waits on SMTP; a failure for one
    // signer does not block the others (they show "Email belum terkirim" with a resend button).
    private function notifyNext(DocusignDocument $doc): void
    {
        $doc->load('signers');
        $ids = $doc->signers
            ->filter(fn ($s) => $s->status === 'pending' && !$s->notified_at && $this->canSign($doc, $s))
            ->pluck('id')
            ->all();
        if (!$ids) {
            return;
        }

        $docId = $doc->id;
        app()->terminating(function () use ($docId, $ids) {
            @set_time_limit(0);
            $doc = DocusignDocument::with('signers')->find($docId);
            if (!$doc) {
                return;
            }
            foreach ($ids as $id) {
                $signer = $doc->signers->firstWhere('id', $id);
                if (!$signer || $signer->notified_at) {
                    continue;
                }
                try {
                    $this->sendSignRequest($doc, $signer);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }

    private function formatDoc(DocusignDocument $doc): array
    {
        $doc->loadMissing(['signers', 'fields']);
        $data = $doc->toArray();
        $data['verify_url'] = DocusignPdfService::verifyUrl($doc);

        return $data;
    }

    // ---------- Authenticated ----------

    public function index()
    {
        $docs = DocusignDocument::with('signers')
            ->where('created_by', auth()->user()->email)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $docs]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf|max:128000',
            'signers' => 'required|string',
            'fields' => 'required|string',
        ]);
        if ($v->fails()) {
            return response()->json(['message' => $v->errors()->first()], 422);
        }

        $signers = json_decode($request->signers, true);
        $fields = json_decode($request->fields, true);
        if (!is_array($signers) || !count($signers) || !is_array($fields) || !count($fields)) {
            return response()->json(['message' => 'Penanda tangan dan posisi tanda tangan wajib diisi'], 422);
        }

        $emails = [];
        $mode = $request->input('sign_mode') === 'sequential' && count($signers) > 1 ? 'sequential' : 'parallel';
        foreach ($signers as $k => $s) {
            $email = strtolower(trim($s['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || trim($s['name'] ?? '') === '') {
                return response()->json(['message' => 'Nama dan email penanda tangan harus valid'], 422);
            }
            if (trim($s['position'] ?? '') === '') {
                return response()->json(['message' => 'Jabatan penanda tangan wajib diisi'], 422);
            }
            if (in_array($email, $emails, true)) {
                return response()->json(['message' => 'Email penanda tangan tidak boleh duplikat'], 422);
            }
            $emails[] = $email;
            $order = $mode === 'sequential' ? (int) ($s['order'] ?? $k + 1) : 1;
            if ($order < 1) {
                return response()->json(['message' => 'Urutan tanda tangan tidak valid'], 422);
            }
            $signers[$k]['order'] = $order;
        }

        $covered = [];
        foreach ($fields as $f) {
            $si = $f['signer'] ?? null;
            if (!isset($signers[$si]) || !isset($f['page'], $f['x'], $f['y'], $f['w'], $f['h'])
                || $f['page'] < 1 || $f['x'] < 0 || $f['y'] < 0 || $f['w'] <= 0 || $f['h'] <= 0
                || $f['x'] + $f['w'] > 1.001 || $f['y'] + $f['h'] > 1.001) {
                return response()->json(['message' => 'Posisi tanda tangan tidak valid'], 422);
            }
            $covered[$si] = true;
        }
        if (count($covered) !== count($signers)) {
            return response()->json(['message' => 'Setiap penanda tangan harus memiliki minimal satu posisi tanda tangan'], 422);
        }

        $file = $request->file('file');
        $uuid = (string) Str::uuid();
        $path = $file->storeAs('docusign/' . $uuid, 'original.pdf', 'local');

        try {
            $doc = DB::connection('adm')->transaction(function () use ($request, $signers, $fields, $uuid, $path, $file, $mode) {
                $doc = DocusignDocument::create([
                    'uuid' => $uuid,
                    'title' => $request->title,
                    'sign_mode' => $mode,
                    'original_path' => $path,
                    'original_hash' => hash_file('sha256', $file->getRealPath()),
                    'created_by' => auth()->user()->email,
                ]);

                $created = [];
                foreach ($signers as $i => $s) {
                    $created[$i] = $doc->signers()->create([
                        'name' => trim($s['name']),
                        'email' => strtolower(trim($s['email'])),
                        'position' => trim($s['position']),
                        'sign_order' => $s['order'],
                        'token' => Str::random(64),
                    ]);
                }
                foreach ($fields as $f) {
                    $doc->fields()->create([
                        'signer_id' => $created[$f['signer']]->id,
                        'page' => (int) $f['page'],
                        'x' => $f['x'], 'y' => $f['y'], 'w' => $f['w'], 'h' => $f['h'],
                    ]);
                }

                return $doc;
            });

            // Validate the PDF is readable by the stamping library before notifying anyone
            DocusignPdfService::render($doc, $this->disk()->path($path), $this->disk()->path('docusign/' . $uuid . '/signed.pdf'));
            $doc->update(['signed_path' => 'docusign/' . $uuid . '/signed.pdf']);
        } catch (\Throwable $e) {
            report($e);
            $this->disk()->deleteDirectory('docusign/' . $uuid);
            DocusignDocument::where('uuid', $uuid)->delete();

            return response()->json(['message' => 'Dokumen tidak dapat diproses: ' . $e->getMessage()], 422);
        }

        $this->notifyNext($doc);

        return response()->json([
            'message' => 'Dokumen dibuat, email permintaan tanda tangan sedang dikirim',
            'data' => $this->formatDoc($doc),
        ], 201);
    }

    public function show($id)
    {
        $doc = DocusignDocument::where('created_by', auth()->user()->email)->findOrFail($id);

        return response()->json(['data' => $this->formatDoc($doc)]);
    }

    public function file(Request $request, $id)
    {
        $doc = DocusignDocument::where('created_by', auth()->user()->email)->findOrFail($id);
        $path = $request->query('type') === 'original' ? $doc->original_path : ($doc->signed_path ?: $doc->original_path);

        return $this->disk()->response($path, Str::slug($doc->title) . '.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function resend($id, $signerId)
    {
        $doc = DocusignDocument::where('created_by', auth()->user()->email)->findOrFail($id);
        if ($doc->status !== 'pending') {
            return response()->json(['message' => 'Dokumen sudah tidak aktif'], 422);
        }
        $signer = $doc->signers()->where('status', 'pending')->findOrFail($signerId);
        $doc->load('signers');
        if (!$this->canSign($doc, $signer)) {
            return response()->json(['message' => 'Belum giliran penanda tangan ini'], 422);
        }
        try {
            $this->sendSignRequest($doc, $signer);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Email gagal terkirim, coba lagi beberapa saat'], 502);
        }
    }

    public function cancel($id)
    {
        $doc = DocusignDocument::where('created_by', auth()->user()->email)->findOrFail($id);
        if ($doc->status !== 'pending') {
            return response()->json(['message' => 'Dokumen tidak dapat dibatalkan'], 422);
        }
        $doc->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Dokumen dibatalkan']);
    }

    // ---------- Public (token based) ----------

    private function signerByToken(string $token): DocusignSigner
    {
        return DocusignSigner::where('token', $token)->firstOrFail();
    }

    public function signInfo($token)
    {
        $signer = $this->signerByToken($token);
        $doc = $signer->document;
        $doc->load('signers');
        $waiting = $doc->signers
            ->filter(fn ($s) => $s->sign_order < $signer->sign_order && $s->status !== 'signed')
            ->map(fn ($s) => ['name' => $s->name, 'position' => $s->position])->values();

        return response()->json(['data' => [
            'title' => $doc->title,
            'status' => $doc->status,
            'sign_mode' => $doc->sign_mode,
            'can_sign' => $doc->status === 'pending' && $signer->status === 'pending' && $waiting->isEmpty(),
            'waiting_for' => $waiting,
            'signer' => ['name' => $signer->name, 'position' => $signer->position, 'email' => $signer->email, 'status' => $signer->status, 'signed_at' => $signer->signed_at],
            'fields' => $doc->fields()->where('signer_id', $signer->id)->get(['page', 'x', 'y', 'w', 'h']),
            'others' => $doc->fields()->where('signer_id', '!=', $signer->id)->get(['signer_id', 'page', 'x', 'y', 'w', 'h']),
        ]]);
    }

    public function signFile($token)
    {
        $signer = $this->signerByToken($token);
        $doc = $signer->document;
        $path = $doc->signed_path ?: $doc->original_path;

        return $this->disk()->response($path, 'dokumen.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function sign(Request $request, $token)
    {
        $signer = $this->signerByToken($token);

        $result = DB::connection('adm')->transaction(function () use ($signer, $request) {
            $doc = DocusignDocument::lockForUpdate()->find($signer->document_id);
            $signer = DocusignSigner::lockForUpdate()->find($signer->id);

            if ($doc->status !== 'pending') {
                return ['error' => 'Dokumen sudah tidak aktif', 'code' => 422];
            }
            if ($signer->status === 'signed') {
                return ['error' => 'Anda sudah menandatangani dokumen ini', 'code' => 422];
            }

            $doc->load(['signers', 'fields']);
            if (!$this->canSign($doc, $signer)) {
                return ['error' => 'Belum giliran Anda. Dokumen harus ditandatangani penanda tangan sebelumnya terlebih dahulu', 'code' => 422];
            }

            $signer->update(['status' => 'signed', 'signed_at' => now(), 'signed_ip' => $request->ip()]);
            $doc->load('signers');
            DocusignPdfService::render(
                $doc,
                $this->disk()->path($doc->original_path),
                $this->disk()->path('docusign/' . $doc->uuid . '/signed.pdf')
            );

            $done = $doc->signers->every(fn ($s) => $s->status === 'signed');
            $doc->update([
                'signed_path' => 'docusign/' . $doc->uuid . '/signed.pdf',
                'status' => $done ? 'completed' : 'pending',
                'completed_at' => $done ? now() : null,
            ]);

            return ['ok' => true, 'doc' => $doc];
        });

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], $result['code']);
        }

        $this->notifyNext($result['doc']);

        return response()->json(['message' => 'Dokumen berhasil ditandatangani']);
    }

    public function verify($uuid)
    {
        $doc = DocusignDocument::with('signers')->where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => [
            'uuid' => $doc->uuid,
            'title' => $doc->title,
            'status' => $doc->status,
            'created_at' => $doc->created_at,
            'completed_at' => $doc->completed_at,
            'original_hash' => $doc->original_hash,
            'signers' => $doc->signers->map(fn ($s) => [
                'name' => $s->name,
                'position' => $s->position,
                'order' => $s->sign_order,
                'status' => $s->status,
                'signed_at' => $s->signed_at,
                'code' => $s->status === 'signed' ? DocusignPdfService::signatureCode($doc, $s) : null,
            ]),
            'sign_mode' => $doc->sign_mode,
        ]]);
    }

    public function verifyFile($uuid)
    {
        $doc = DocusignDocument::where('uuid', $uuid)->firstOrFail();

        return $this->disk()->response($doc->signed_path ?: $doc->original_path, Str::slug($doc->title) . '.pdf', ['Content-Type' => 'application/pdf']);
    }
}
