<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth2Controller;
use App\Http\Controllers\Employe\EmployeController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Task\TaskController;
use App\Http\Controllers\Comment\CommentController;
use App\Http\Controllers\Mitra\MitraController;
use App\Http\Controllers\Tickets\TicketController;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Auth Route
Route::controller(AuthController::class)->group(function(){
    Route::post('/auth/register', 'register');
    Route::post('/auth/login', 'login');
    Route::get('/auth/logout', 'logout');
    Route::get('/auth/refresh', 'refresh');
    Route::get('auth/welcome/test', 'welcome');
    Route::get('auth/change-pas', 'changePas');
});

Route::controller(Auth2Controller::class)->group(function(){
    Route::post('/auth2/register', 'register');
    Route::post('/auth2/login', 'login');
    Route::get('/auth2/logout', 'logout');
    Route::get('/auth2/refresh', 'refresh');
    Route::get('/auth2/resend/{id}', 'kirimEmail');
    Route::get('/auth2/verif/{id}', 'verifEmail');
});


// Employee Routes
Route::controller(EmployeController::class)->group(function(){
    Route::get("/employe", 'index')->middleware("role:Employee");
    Route::get("/employe/assignment-list", 'assignmentList')->middleware("role:Employee");
    Route::post('/employe', 'store');
    Route::patch('/employe/{employe_id}', 'update');
    Route::get('/employe/{employe_id}', 'show');
    Route::delete("/employe/{employe_id}", 'destroy');
    Route::get("/employe/division/{employe_id}", 'getEmployeDivision');
    Route::get("/employe/notification/list", 'notification')->middleware("role:Employee");
    Route::patch("/employe/notification/read/{notif_id}", 'updateNotif')->middleware("role:Employee");
    Route::get("/employe/structure/check/{emploId}", 'checkStructure');
    Route::get("/employe/division-member/{manager_id}", "employeesByDivision")->middleware("role:Manager");
});

// Project routes
Route::controller(ProjectController::class)->group(function(){
    Route::get("/project", 'index')->middleware("role:Employee");
    Route::get("/project/{project_id}", 'show')->middleware("role:Employee");
    Route::get("/project/business/options", 'businessOptions')->middleware("role:Employee");
    Route::get("/project/partner/options", 'partnerOptions')->middleware("role:Employee");
    Route::post("/project", 'store')->middleware("role:Staff,Manager");
    Route::put("/project/{project_id}/update", 'update')->middleware("role:Staff,Manager");
    Route::get("/project/{project_id}/history", 'history')->middleware("role:Employee");
    Route::get("/project/{project_id}/members", 'members')->middleware("role:Employee");
    Route::get("/project/{project_id}/files", "files")->middleware("role:Employee");
    Route::post("/project/handover/", "handOver")->middleware("role:Manager");
    Route::get("/project/{employe_id}/{project_id}/handover", "getHandover")->middleware("role:Manager");
    Route::post("/project/{history_id}/confirm", "handoverConfirm")->middleware("role:Manager");
    Route::get("/project/{project_id}/{employe_id}/bast/review", "bastReview")->middleware("role:Director");
    Route::post("/project/{history_id}/bast/approval", "bastApproval")->middleware("role:Director");
    Route::get("/project/{employe_id}/total-data/", "totalDataByEmploye")->middleware("role:Staff,Manager,Director");
    Route::get("/project/{employe_id}/list/", "projectByEmployeDivision")->middleware("role:Staff,Manager,Director");
    Route::get("/project/timeline/list-data", "timelineData")->middleware("role:Staff,Manager");
    Route::delete("/project/{project_id}", "destroy")->middleware("role:Staff,Manager");
});

// Task routes
Route::controller(TaskController::class)->group(function(){
    Route::get("/task", "index");
    Route::post("/task", "store")->middleware("role:Employee");
    Route::get("/task/history/{task_id}", "taskHistory")->middleware('role:Employee');
    Route::get("/task/{project_id}/{task_id}", "show")->middleware("role:Employee");
    Route::patch("/task/{task_id}", "update")->middleware("role:Employee");
    Route::put("/task/{task_id}/status", "updateStatus")->middleware("role:Employee,Manager");
    Route::get("/task/{project_id}", "getTodo")->middleware("role:Employee");
    Route::get("/task/{project_id}/employe/all", "taskByEmploye")->middleware("role:Employee");
    Route::delete("task/{task_id}", 'destroy')->middleware("role:Employee");
    Route::post("task/{task_id}/upload", 'upload')->middleware("role:Employee");
    Route::get("task/{project_id}/level1/review", 'review')->middleware("role:Manager,Director");
    Route::get("task/{project_id}/activities/all", 'taskByProject')->middleware("role:Manager,Director");
    Route::get("task/{employe_id}/recent/activity", 'recentTaskByEmploye')->middleware("role:Staff,Manager");
    Route::post("task/{employe_id}/{task_id}/favorite", 'addFavoriteTask')->middleware("role:Director");
    Route::get("task/{employe_id}/favorite/list", 'favoriteList')->middleware("role:Director");
});
// Route::get("/employe", [EmployeController::class, "index"])->middleware('role:Admin');

// Task comment routes
Route::controller(CommentController::class)->group(function(){
    Route::get("/comment/{taskId}", "index")->middleware("role:Employee");
    Route::post("/comment", "store")->middleware("role:Employee");
    Route::patch("/comment/{commentId}", "update")->middleware("role:Employee");
    Route::delete("/comment/{commentId}/{employe_id}", "destroy")->middleware("role:Employee");
});

// Ticket routes
Route::controller(TicketController::class)->group(function(){
    Route::get("/ticket", "index")->middleware("role:Employee");
    Route::post("/ticket", "store")->middleware("role:Employee");
    Route::patch("/ticket/{ticketId}", "update")->middleware("role:Manager");
    Route::get("/ticket/employe", "getTicketByEmploye")->middleware("role:Employee");
    Route::get("/ticket/manager", "getRequestByManager")->middleware("role:Manager");
    Route::post("/ticket/assign-task", "assignTask")->middleware("role:Manager");
});

// Mitra
Route::controller(MitraController::class)->group(function(){
    Route::get('/list-mitra', "index")->middleware("role:Employee");
});
