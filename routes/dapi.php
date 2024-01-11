<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Da\DaCatController;
use App\Http\Controllers\Da\DaActController;
use App\Http\Controllers\Da\DaActivitController;
use App\Http\Controllers\Asset\InvCat;
use App\Http\Controllers\Asset\InvController;
use App\Http\Controllers\Vendors\Admin\APerusahaanController;
use App\Http\Controllers\Vendors\Admin\ATenderController;

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
     Route::get('inven/{id}', 'show')->middleware("role:PicAsset");
     Route::post('inven/child/del', 'deleteChild')->middleware("role:PicAsset");
     Route::post('inven/child/add', 'addChild')->middleware("role:PicAsset");
     Route::post('inven/update/image', 'changeImage')->middleware("role:PicAsset");
     Route::get('inv/onme', 'getAssetOnMe')->middleware("role:Employee");
     Route::post('inv/rservice', 'requestService')->middleware("role:Employee");
     Route::get('inv/getrservice', 'getRequest')->middleware("role:Employee");
});

// vendor admin

Route::controller(APerusahaanController::class)->group(function(){
     Route::get('vendor/company', 'index')->middleware("role:AdminVendor");
     Route::get('vendor/company/{id}', 'show')->middleware("role:AdminVendor");
     Route::get('vendor/request-list', 'requestList')->middleware("role:AdminVendor");
     Route::get('vendor/{id}/list-data-umum', 'listDataUmum')->middleware("role:AdminVendor");
     Route::get('vendor/{id}/list-jajaran', 'listJajaran')->middleware("role:AdminVendor");
     Route::get('vendor/{id}/list-akta', 'listAkta')->middleware("role:AdminVendor");
     Route::get('vendor/{id}/list-izin', 'listIzin')->middleware("role:AdminVendor");
     Route::get('vendor/{id}/list-dokumen', 'listDokumen')->middleware("role:AdminVendor");
     Route::get('vendor/{id}/list-portofolio', 'listPortofolio')->middleware("role:AdminVendor");
     Route::get('vendor/{id}/list-kbli', 'listKbli')->middleware("role:AdminVendor");
     Route::put('vendor/{id}/update-status', 'updateStatus')->middleware("role:AdminVendor");
     Route::post('vendor/sendmail', 'sendEmail')->middleware("role:AdminVendor");
     Route::post('vendor/verifikasi/{id}', 'verif')->middleware("role:AdminVendor");
     Route::get('vendor/log/{id}', 'getLog')->middleware("role:AdminVendor");
     Route::get('vendor/masterkbli', 'list')->middleware("role:AdminVendor");
});

Route::controller(ATenderController::class)->group(function(){
     Route::post('vendor/tender', 'store')->middleware("role:AdminVendor");
     Route::get('vendor/tender', 'index')->middleware("role:AdminVendor");
     Route::get('vendor/tender/{id}', 'show')->middleware("role:AdminVendor");
     Route::post('vendor/tender/update', 'update')->middleware("role:AdminVendor");
     Route::post('vendor/tender/delete/{id}', 'deleteTender')->middleware("role:AdminVendor");
     Route::get('vendor/tender/peserta/{id}', 'showPer')->middleware("role:AdminVendor");
     Route::post('vendor/tender/tahapdua/{id}', 'setTahap2')->middleware("role:AdminVendor");
     Route::post('vendor/tender/pemenang/{id}', 'setTahap2')->middleware("role:AdminVendor");
     Route::get('vendor/tender/tahapdua/{id}', 'getTahap2')->middleware("role:AdminVendor");
});