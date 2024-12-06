<?php

use App\Http\Controllers\Adm\StaticAdmController;
use App\Http\Controllers\Adm\SuratController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Da\DaCatController;
use App\Http\Controllers\Da\DaActController;
use App\Http\Controllers\Da\DaActivitController;
use App\Http\Controllers\Asset\InvCat;
use App\Http\Controllers\eSign\Logs;
use App\Http\Controllers\Vendors\Admin\APerusahaanController;
use App\Http\Controllers\Vendors\Admin\ATenderController;

use App\Http\Controllers\Sppd\StaticDataController;
use App\Http\Controllers\Sppd\PengajuanController;
use App\Http\Controllers\Verify\ScanVerif;
use App\Http\Middleware\Role;

Route::controller(DaCatController::class)->group(function () {
     Route::get('/categories', 'index')->middleware("role:Employee");
});

Route::controller(DaActController::class)->group(function () {
     Route::get('/activities', 'index')->middleware("role:Employee");
});

Route::controller(DaActivitController::class)->group(function () {
     Route::post('/activit', 'store')->middleware("role:Employee");
     Route::get('/myactivity/{filter}', 'index')->middleware("role:Employee");
     Route::post('myactivity/update', 'updateMember')->middleware("role:Employee");
     Route::post('myactivity/progress', 'updateProg')->middleware("role:Employee");
     Route::post('myactivity/delete', 'deleteProg')->middleware("role:Employee");
     Route::get('mustreview', 'getReview')->middleware("role:Employee");
     Route::post('mustreview/review', 'changeStatus')->middleware("role:Employee");
     Route::get('myteam/activities/{filter}', 'getTeamAct')->middleware("role:Employee");
     Route::get('all/activities/{filter}', 'allActivit')->middleware("role:AllDaily");
});

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
     Route::get('vendor/tender/approval/ba', 'approvalBa')->middleware("role:Manager,Director");
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
     Route::post('sppd/pengajuan/realisasi', 'submitRealisasi')->middleware("role:Employee");
     Route::post('sppd/pengajuan/done', 'done')->middleware("role:Employee");
     Route::get('sppd/listsharing', 'getNomorSppd')->middleware("role:Employee");
     Route::get('sppd/dashboard', 'dataDashboard')->middleware("role:Employee");
});

// Verification Document

Route::controller(ScanVerif::class)->group(function(){
     Route::post('verif/{id_doc}', 'getDetail');
});

// ADM
Route::controller(StaticAdmController::class)->group(function(){
     Route::get('adm/divisi', 'getDivisi')->middleware("role:Employee");
     Route::get('adm/signers/{id}', 'getSigner')->middleware("role:Employee");
});

Route::controller(SuratController::class)->group(function(){
     Route::post('adm/insert', 'insert')->middleware("role:Employee");
     Route::get('adm/surat/{what}', 'getSurat')->middleware("role:Employee");
     Route::get('adm/surat/detail/{id}', 'detail')->middleware("role:Employee");
     Route::post("adm/surat/review/{id_doc}", 'reviewDokumen')->middleware(     'role:Employee');
     Route::post('adm/update', 'update')->middleware('role:Employee');
});


Route::controller(Logs::class)->group(function(){
     Route::get("eSign/logs/{id_doc}", "getLogs")->middleware(  "role:Employee");
     Route::get("eSign/approval/{id_doc}", "getApproval")->middleware("role:Employee");
});