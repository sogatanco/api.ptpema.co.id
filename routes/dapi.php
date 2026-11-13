<?php

use App\Http\Controllers\Adm\StaticAdmController;
use App\Http\Controllers\Adm\SuratController;
use App\Http\Controllers\Adm\SuratMasuk;
use App\Http\Controllers\Hr\AbsensiController;
use App\Http\Controllers\Hr\DashboardTvController;
use App\Http\Controllers\Hr\ProfilController;
use App\Http\Controllers\MobilOp\MobilController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Asset\InvCat;
use App\Http\Controllers\Asset\InvController;
use App\Http\Controllers\eSign\Logs;
use App\Http\Controllers\Docusign\DocusignController;
use App\Http\Controllers\Vendors\Admin\APerusahaanController;
use App\Http\Controllers\Vendors\Admin\ATenderController;
use App\Http\Controllers\Meeting\MeetingController;
use App\Http\Controllers\Wa\SendMessageController;

use App\Http\Controllers\Sppd\StaticDataController;
use App\Http\Controllers\Sppd\PengajuanController;
use App\Http\Controllers\Verify\ScanVerif;
use App\Http\Middleware\Role;
use App\Http\Controllers\Kontrak\ContractController;

Route::controller(InvCat::class)->group(function () {
     Route::get('inven/category', 'index')->middleware("role:Employee");
});

Route::controller(InvController::class)->group(function () {
     Route::post('inven/add', 'store')->middleware("role:PicAsset");
     Route::post('inven/delete', 'deleteAsset')->middleware("role:PicAsset");
     Route::post('inven/update', 'updateAsset')->middleware("role:PicAsset");
     Route::post('inven/child/update', 'uChild')->middleware("role:PicAsset");
     Route::get('inven', 'index')->middleware("role:PicAsset");
     Route::get('inven/{id}', 'show');
     Route::post('inven/child/del', 'deleteChild')->middleware("role:PicAsset");
     Route::post('inven/child/add', 'addChild')->middleware("role:PicAsset");
     Route::post('inven/update/image', 'changeImage')->middleware("role:PicAsset");
     Route::get('inv/onme', 'getAssetOnMe')->middleware("role:Employee");
     Route::post('inv/rservice', 'requestService')->middleware("role:Employee");
     Route::get('inv/getrservice', 'getRequest')->middleware("role:Employee");
     Route::post('inven/service/update', 'updateStatus')->middleware("role:PicAsset");
     Route::post('inven/service/upload', 'uploadBukti')->middleware("role:PicAsset");
     Route::post('inven/service/done', 'doneService')->middleware("role:PicAsset");
});

// vendor admin & viewer
Route::controller(APerusahaanController::class)->group(function(){
     Route::get('vendor/company', 'index')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::get('vendor/company/{id}', 'show')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::get('vendor/request-list', 'requestList')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::get('vendor/{id}/list-data-umum', 'listDataUmum')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::get('vendor/{id}/list-jajaran', 'listJajaran')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::get('vendor/{id}/list-akta', 'listAkta')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::get('vendor/{id}/list-izin', 'listIzin')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::get('vendor/{id}/list-dokumen', 'listDokumen')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::get('vendor/{id}/list-portofolio', 'listPortofolio')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::get('vendor/{id}/list-kbli', 'listKbli')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::put('vendor/{id}/update-status', 'updateStatus')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::post('vendor/sendmail', 'sendEmail')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::post('vendor/verifikasi/{id}', 'verif')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::get('vendor/log/{id}', 'getLog')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::get('vendor/masterkbli', 'list')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::get('vendor/companies-to-invite', 'companiesToInvite')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::get('vendor/company-verify-status/{id}', 'getCompanyStatus')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
});

Route::controller(ATenderController::class)->group(function(){
     Route::post('vendor/tender', 'store')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::get('vendor/tender', 'index')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::get('vendor/tender/{id}', 'show')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::put('vendor/tender/update', 'update')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::post('vendor/tender/delete/{id}', 'deleteTender')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::get('vendor/tender/peserta/{id}', 'showPer')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::post('vendor/tender/tahapdua/{id}', 'setTahap2')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::post('vendor/tender/pemenang/{id}', 'setPemenang')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::get('vendor/tender/tahapdua/{id}', 'getTahap2')->middleware("role:AdminVendorUmum,AdminVendorScm,VendorViewer");
     Route::post('vendor/tender/ba/{id}', 'ba')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::post('vendor/tender/status-update/{id}', 'updateTenderStatus')->middleware("role:AdminVendorUmum,AdminVendorScm");
     Route::get('vendor/tender/approval/ba', 'approvalBa')->middleware("role:Supervisor,Manager,Director");
     Route::post('vendor/tender/approval-ba/ba/{id}', 'approveBaByManager')->middleware("role:Manager");

});

// SPPD

Route::controller(StaticDataController::class)->group(function(){
     Route::get('sppd/static/category', 'getCategori')->middleware("role:Employee");
     Route::get('sppd/static/pihak', 'getPihak')->middleware("role:Employee");
     Route::get('sppd/static/jenis', 'getJenis')->middleware("role:Employee");
     Route::get('sppd/static/dasar', 'getDasar')->middleware("role:Employee");
     Route::get('sppd/static/employee/{employeeID}', 'getDeatailEmployee')->middleware("role:Employee");
     Route::get('sppd/static/klasifikasi', 'getKlasifikasiBisnis')->middleware("role:Employee");
     Route::get('sppd/static/sumber', 'getSumber')->middleware("role:Employee");
     Route::get('sppd/static/renbis', 'getRenbis')->middleware("role:Employee");
});



Route::controller(PengajuanController::class)->group(function(){
     Route::post('sppd/pengajuan', 'store')->middleware("role:Employee");
     Route::get('sppd/pengajuan', 'getSubmitted')->middleware("role:Employee");
     Route::get('sppd/pengajuan/{id}', 'getDetail')->middleware("role:Employee");
     Route::post('sppd/pengajuan/update/{id}', 'updatePengajuan')->middleware("role:Employee");
     Route::post('sppd/pengajuan/review/{id_doc}', 'persetujuan')->middleware("role:Employee");
    Route::post('sppd/pengajuan/sign-pdf/{id}', 'signPdf')->middleware("role:Employee");
     Route::post('sppd/pengajuan/realisasi', 'submitRealisasi')->middleware("role:Employee");
     Route::post('sppd/pengajuan/done', 'done')->middleware("role:Employee");
     Route::post('sppd/pengajuan/{id}/void', 'void')->middleware("role:Employee");
     Route::get('sppd/listsharing', 'getNomorSppd')->middleware("role:Employee");
     Route::get('sppd/dashboard', 'dataDashboard')->middleware("role:Employee");
     Route::get('sppd/tujuan/{id}', 'getTujuanById')->middleware("role:Employee");
     Route::post('sppd/ekstend', 'insertEkstend')->middleware("role:Employee");
     Route::post('sppd/tiga-puluh', 'tgaPlhPersen')->middleware("role:Employee");
});

// Tambahkan route untuk kontrak
Route::controller(ContractController::class)->group(function(){
     Route::get('kontrak', 'index')->middleware("role:Employee");
     Route::get('kontrak/{id}', 'show')->middleware("role:Employee");
     Route::get('kontrak/{id}/history', 'history')->middleware("role:Employee");
     Route::post('kontrak', 'store')->middleware("role:Employee");
     Route::post('kontrak/update/{id}', 'update')->middleware("role:Employee");
     Route::post('kontrak/delete/{id}', 'destroy')->middleware("role:Employee");
});

// Verification Document

Route::controller(ScanVerif::class)->group(function(){
     Route::post('verif/{id_doc}', 'getDetail');
});

// ADM
Route::controller(StaticAdmController::class)->group(function(){
     Route::get('adm/divisi', 'getDivisi')->middleware("role:Employee");
     Route::get('adm/signers/{id}', 'getSigner')->middleware("role:Employee");
     Route::get("adm/direkturs", "getDirektur")->middleware(    "role:Employee");
     Route::get('adm/dispo/to', 'getDispo')->middleware('role:Employee');
     Route::get('adm/dashboard/{dt}', 'dashboard')->middleware('role:Employee');
});

Route::controller(\App\Http\Controllers\Sppd\RenbisController::class)->group(function(){
     Route::get('renbis/akses', 'akses')->middleware("role:Employee");
     Route::get('renbis', 'index')->middleware("role:Employee");
     Route::post('renbis', 'store')->middleware("role:Employee");
     Route::post('renbis/{id}/update', 'update')->middleware("role:Employee");
     Route::post('renbis/{id}/hapus', 'destroy')->middleware("role:Employee");
});

Route::controller(\App\Http\Controllers\Adm\SuratBackdateController::class)->group(function(){
     Route::post('adm/backdate/ajukan', 'ajukan')->middleware("role:AdminAdm");
     Route::get('adm/backdate/daftar', 'daftar')->middleware("role:AdminAdm,PICAdm");
     Route::get('adm/backdate/{id}/saran', 'saran')->middleware("role:PICAdm");
     Route::post('adm/backdate/{id}/setujui', 'setujui')->middleware("role:PICAdm");
     Route::post('adm/backdate/{id}/tolak', 'tolak')->middleware("role:PICAdm");
});

Route::controller(\App\Http\Controllers\Adm\SuratDraftController::class)->group(function(){
     Route::post('adm/draft/simpan', 'simpan')->middleware("role:AdminAdm");
     Route::get('adm/draft/daftar', 'daftar')->middleware("role:AdminAdm");
     Route::get('adm/draft/{id}', 'detail')->middleware("role:AdminAdm");
     Route::post('adm/draft/{id}/hapus', 'hapus')->middleware("role:AdminAdm");
});

Route::controller(SuratController::class)->group(function(){
     Route::post('adm/insert', 'insert')->middleware("role:Employee");
     Route::get('adm/surat/{what}', 'getSurat')->middleware("role:Employee");
     Route::get('adm/surat/detail/{id}', 'detail')->middleware("role:Employee");
     Route::post("adm/surat/review/{id_doc}", 'reviewDokumen')->middleware(     'role:Employee');
     Route::post('adm/surat/sign-pdf/{id}', 'signPdf')->middleware('role:Employee');
     Route::post('adm/update', 'update')->middleware('role:Employee');
     Route::post('adm/void/{id}', 'void')->middleware('role:AdminAdm,PICAdm');
     Route::post('adm/surat/{id}/ttd-basah', 'uploadTtdBasah')->middleware('role:AdminAdm,PICAdm');
     Route::get('adm/surat/{id}/ttd-basah', 'unduhTtdBasah')->middleware('role:Employee');
     Route::post('adm/read/{id_doc}', 'readSurat')->middleware('role:Employee');
});


Route::controller(DocusignController::class)->group(function () {
     Route::get('docusign', 'index')->middleware('role:Employee');
     Route::post('docusign', 'store')->middleware('role:Employee');
     Route::get('docusign/detail/{id}', 'show')->middleware('role:Employee');
     Route::get('docusign/detail/{id}/file', 'file')->middleware('role:Employee');
     Route::post('docusign/detail/{id}/cancel', 'cancel')->middleware('role:Employee');
     Route::post('docusign/detail/{id}/resend/{signerId}', 'resend')->middleware('role:Employee');

     // Public: signer link (token) and QR verification
     Route::middleware('throttle:60,1')->group(function () {
          Route::get('docusign/public/sign/{token}', 'signInfo');
          Route::get('docusign/public/sign/{token}/file', 'signFile');
          Route::post('docusign/public/sign/{token}', 'sign');
          Route::get('docusign/public/verify/{uuid}', 'verify');
          Route::get('docusign/public/verify/{uuid}/file', 'verifyFile');
     });
});

// Rekap SPPD yang sudah di-approve (aktif dan void), per tahun
Route::get('sppd/rekap', [\App\Http\Controllers\Sppd\RekapSppdController::class, 'index'])->middleware('role:AdminSPPD,UmumSppd,KeuanganSppd,Director');

// Klaim hotel 30% untuk pelaksana SPPD bukan karyawan: sisi pengaju (login) dan tautan email publik (token)
Route::controller(\App\Http\Controllers\Sppd\Hotel30TamuController::class)->group(function () {
    Route::get('sppd/tiga-puluh/{idTujuan}/pelaksana', 'info')->middleware('role:Employee');
    Route::post('sppd/tiga-puluh/{idTujuan}/kirim', 'kirim')->middleware('role:Employee');
    Route::post('sppd/tiga-puluh/{idTujuan}/manual', 'manual')->middleware('role:Employee');
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('sppd/public/hotel30/{token}', 'publikInfo');
        Route::post('sppd/public/hotel30/{token}', 'publikJawab');
    });
});

Route::controller(Logs::class)->group(function(){
     Route::get("eSign/logs/{id_doc}", "getLogs")->middleware(  "role:Employee");
     Route::get("eSign/approval/{id_doc}", "getApproval")->middleware("role:Employee");
});

Route::controller(SuratMasuk::class)->group(function(){
     Route::post("adm/suratmasuk/", "insert")->middleware("role:Employee");
     Route::get('adm/suratmasuk/{what}', 'getSM')->middleware('role:Employee');
     Route::get('adm/suratmasuk/detail/{id}', 'getDetail')->middleware('role:Employee');
     Route::post('adm/suratmasuk/diposisi/{idSurat}', 'disposisi')->middleware('role:Employee');
     Route::post('adm/suratmasuk/delete/{id}', 'delete')->middleware('role:Employee');
     Route::get('adm/suratmasuk/riwayat/{id}', 'getRiwayat')->middleware('role:Employee');
     Route::post('adm/suratmasuk/read/{id_cc}', 'readCC')->middleware('role:Employee');
     // Publik: status surat masuk dari QR nomor agenda
     Route::get('adm/public/agenda/{token}', 'statusAgenda')->middleware('throttle:60,1');
});

// HR
Route::controller(ProfilController::class)->group(function(){
     Route::get('hr/profil/{employe_id}', 'getImage');

});

Route::controller(AbsensiController::class)->group(function(){
     Route::post('hr/clock_in', 'clock_in')->middleware("role:Employee");
     Route::get('hr/office', 'getOffice')->middleware("role:Employee");
     Route::get('hr/dashboard-absensi', 'dashboard')->middleware("role:Employee");
     Route::get('hr/dashboard-absensi/statistik', 'statistik')->middleware("role:Employee");
     Route::get('hr/struktur-organisasi', 'strukturOrganisasi')->middleware("role:Employee");
});

Route::controller(DashboardTvController::class)->group(function(){
     Route::get('hr/dashboard-tv/slides', 'slides')->middleware("role:Employee");
     Route::put('hr/dashboard-tv/slides', 'updateSlides')->middleware("role:PICDashboard");
     Route::get('hr/dashboard-tv/lalu-lintas', 'lalulintas')->middleware("role:Employee");
});

Route::controller(MobilController::class)->group(function(){
     Route::post('mobil/insert', 'insert')->middleware("role:Employee");
     Route::get('mobil/get', 'getMobil')->middleware("role:Employee");
     Route::post('mobil/delete/{id}', 'delete')->middleware("role:Employee");
     Route::post('mobil/update/{id}', 'update')->middleware("role:Employee");
     Route::post('/mobil/insert-permintaan',  'insertPermintaan')->middleware("role:Employee");
     Route::get('/mobil/get-permintaan', 'getPermintaan')->middleware("role:Employee");
     Route::get('/mobil/get-permintaan-after', 'getPermintaanAfter')->middleware("role:Employee");

     Route::post('/mobil/delete-permintaan/{id}', 'deletePermintaan')->middleware("role:Employee");
     Route::get('/mobil/get-permintaan-by-status', 'getPermintaanByStatus')->middleware("role:Employee");
     Route::post('/mobil/insert-pengambilan', 'insertPengambilan')->middleware("role:Employee");
     Route::get('/mobil/get-pengambilan', 'getPengambilan')->middleware("role:Employee");
     Route::post('/mobil/pengembalian/{id}', 'pengembalian')->middleware("role:Employee");
     Route::get('/mobil/get-permintaan-all', 'getPermintaanAll')->middleware("role:Employee");
     Route::post('/mobil/approve-permintaan/{id}', 'approvePermintaan')->middleware("role:Employee");
     Route::post('/mobil/reject-permintaan/{id}', 'rejectPermintaan')->middleware("role:Employee");
     Route::post('/mobil/insert-bbm', 'insertBBM')->middleware("role:Employee");
     Route::get('/mobil/get-bbm', 'getBBM')->middleware("role:Employee");
     Route::get('mobil/available', 'getMobilAktifDanTidakDalamPemakaian')->middleware("role:Employee");
     Route::get('mobil/pengambilan-calendar', 'getPengambilanCalendar')->middleware("role:Employee");
     Route::get('mobil/bbm-laporan', 'getBBMLaporan')->middleware("role:Employee");
});

Route::controller(MeetingController::class)->group(function () {
    Route::post('meeting/zoom/book', 'bookZoom')->middleware("role:Employee");
    Route::get('meeting/zoom/list', 'listZoom')->middleware("role:Employee");
    Route::post('meeting/zoom/cancel/{id}', 'cancelZoom')->middleware("role:Employee");

    Route::post('meeting/room/book', 'bookMeetingRoom')->middleware("role:Employee");
    Route::get('meeting/room/list', 'listMeetingBookings');
    Route::post('meeting/room/cancel/{id}', 'cancelMeetingBooking')->middleware("role:Employee");
});

Route::get('send-message/first', [SendMessageController::class, 'getFirst']);
Route::post('notif/{id}/set-swa', [SendMessageController::class, 'setNotifSwaNow']);

