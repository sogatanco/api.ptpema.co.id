<?php 

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Vendors\UserController;
use App\Http\Controllers\Vendors\PerusahaanController;
use App\Http\Controllers\Vendors\TenderController;
use App\Http\Controllers\Vendors\JajaranController;
use App\Http\Controllers\Vendors\AktaController;
use App\Http\Controllers\Vendors\FileController;
use App\Http\Controllers\Vendors\IzinController;
use App\Http\Controllers\Vendors\PortoController;
use App\Http\Controllers\Vendors\KlbiController;


Route::middleware('auth:api_vendor')->get('/uservendor', function (Request $request) {
    return $request->user();
});

Route::controller(UserController::class)->group(function() {
    Route::get('user', 'index');
    Route::post('user/register-company', 'registerCompany');
});

Route::controller(PerusahaanController::class)->group(function() {
    Route::get('perusahaan/status', 'statusPerusahaan');
    Route::get('perusahaan/list-bidang-usaha', 'listBidangUsaha');
    Route::get('perusahaan/data-umum', 'getDataUmum');
    Route::post('perusahaan/store', 'store');
    Route::put('perusahaan/submit', 'submit');
    Route::get('perusahaan/document-status', 'documentStatus');
    Route::get('perusahaan/jajaran-status', 'jajaranStatus');
    Route::get('perusahaan/portofolio-status', 'portofolioStatus');
    Route::get('perusahaan/bidangusaha-status', 'bidangUsahaStatus');
});

Route::controller(JajaranController::class)->group(function() {
    Route::post('jajaran/store', 'store');
    Route::post('jajaran/edit/{id}', 'edit');
    Route::get('jajaran/my', 'myDirek');
    Route::post('jajaran/delete/{id}', 'deleteDir');
});

Route::controller(AktaController::class)->group(function() {
    Route::post('akta/store', 'store');
    Route::get('akta/view', 'viewFile');
    Route::get('akta/delete/{id}', 'deleteAkta');
});

Route::controller(FileController::class)->group(function(){
    Route::post('file/upload','uplaodFile' );
    Route::get('file/dokumen-perusahaan', 'viewFile');
});

Route::controller(IzinController::class)->group(function(){
    Route::post('izin/store', 'store');
    Route::get('izin/my', 'view');
    Route::get('izin/delete/{id}', 'delete');
});

Route::controller(PortoController::class)->group(function(){
    Route::post('porto/store', 'store');
    Route::get('porto/my', 'view');
    Route::get('porto/delete/{id}', 'delete');
});

Route::controller(KlbiController::class)->group(function(){
    Route::post('kbli/store', 'store');
    Route::get('kbli/view', 'myKbli');
    Route::get('kbli/list', 'list');
    Route::delete('kbli/delete/{id}', 'delete');
}); 

Route::controller(TenderController::class)->group(function(){
    Route::get('tender/list-tender', 'listTender');
    Route::post('tender/register', 'ikot');
    Route::post('tender/upload/dokumen', 'upload');
});