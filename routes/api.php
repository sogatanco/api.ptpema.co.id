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
use App\Http\Controllers\Notification\TestingController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\Report\ProjectReportController;
use App\Http\Controllers\File\PreviewController;
use App\Http\Controllers\Master\BoardOrganizationController;
use App\Http\Controllers\Master\OrganizationController;
use App\Http\Controllers\Master\PositionController;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Auth Route
Route::controller(AuthController::class)->group(function(){
    Route::post('/auth/register', 'register');
    Route::post('/auth/login', 'login');
    Route::post('/auth/login-sso', 'loginSso');
    Route::get('/auth/logout', 'logout');
    Route::get('/auth/refresh', 'refresh');
    Route::get('auth/welcome/test', 'welcome');
    Route::post('auth/change-pas', 'changePas');
    Route::post('auth/forgot-password', 'forgotPassword');
    Route::GET('/auth/forgot-password/{token}', 'checkToken');
    Route::post('auth/new-password', 'newPassword');
});

Route::controller(Auth2Controller::class)->group(function(){
    Route::post('/auth2/register', 'register');
    Route::post('/auth2/login', 'login');
    Route::get('/auth2/logout', 'logout');
    Route::get('/auth2/refresh', 'refresh');
    Route::get('/auth2/resend/{id}', 'kirimEmail');
    Route::get('/auth2/verif/{id}', 'verifEmail');
    Route::post('auth2/change-pas', 'changePas');
    Route::post('/auth2/forgot-password', 'forgotPassword');
    Route::GET('/auth2/forgot-password/{token}', 'checkToken');
    Route::post('/auth2/new-password', 'newPassword');
});

// Employee Routes
Route::controller(EmployeController::class)->group(function(){
    Route::get("/employe", 'index')->middleware("role:Admin");
    Route::get("/employe/assignment-list", 'assignmentList')->middleware("role:Employee");
    Route::post('/employe/store', 'store')->middleware("client");
    Route::put('/employe/update/{employe_id}/personal', 'update')->middleware("client");
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
    Route::post("/project/progress/collection", "progressCollection")->middleware("role:Employee");
    Route::get("/project/business/options", 'businessOptions')->middleware("role:Employee");
    Route::get("/project/partner/options", 'partnerOptions')->middleware("role:Employee");
    Route::post("/project", 'store')->middleware("role:Staff,Manager");
    Route::patch("/project/{project_id}/update", 'update')->middleware("role:Staff,Supervisor,Manager");
    Route::get("/project/{project_id}/history", 'history')->middleware("role:Employee");
    Route::get("/project/{project_id}/members", 'members')->middleware("role:Employee");
    Route::get("/project/{project_id}/files", "files")->middleware("role:Employee");
    Route::post("/project/handover/", "handOver")->middleware("role:Manager");
    Route::get("/project/{employe_id}/{project_id}/handover", "getHandover")->middleware("role:Manager");
    Route::post("/project/{history_id}/confirm", "handoverConfirm")->middleware("role:Manager");
    Route::get("/project/{project_id}/{employe_id}/bast/review", "bastReview")->middleware("role:Director");
    Route::post("/project/{history_id}/bast/approval", "bastApproval")->middleware("role:Director");
    Route::get("/project/{employe_id}/total-data/", "totalDataByEmploye")->middleware("role:Staff,Supervisor,Manager,Director");
    Route::get("/project/{employe_id}/list/", "projectByEmployeDivision")->middleware("role:Staff,Supervisor,Manager,Director");
    Route::get("/project/timeline/list-data", "timelineData")->middleware("role:Staff,Supervisor,Manager");
    Route::delete("/project/{project_id}", "destroy")->middleware("role:Staff,Supervisor,Manager");
    Route::post("/project/activity-base/add", "createActivityBase")->middleware("role:Staff,Supervisor,Manager");
    Route::get("/project/manager/assigned/list", "assignedProject")->middleware("role:Manager");
    Route::get("/project/dashboard/recent-update/list", "recentUpdate")->middleware("role:Staff,Supervisor,Manager");
});

// Task routes
Route::controller(TaskController::class)->group(function(){
    Route::get("/task", "index");
    Route::post("/task", "store")->middleware("role:Employee");
    Route::get("/task/history/{task_id}", "taskHistory")->middleware('role:Employee');
    Route::get("/task/{task_id}/show", "show")->middleware("role:Employee");
    Route::patch("/task/{task_id}", "update")->middleware("role:Employee");
    Route::put("/task/{task_id}/status", "updateStatus")->middleware("role:Employee,Supervisor,Manager");
    Route::get("/task/{project_id}", "getTodo")->middleware("role:Employee");
    Route::get("/task/{project_id}/employe/all", "taskByEmploye")->middleware("role:Employee");
    Route::delete("task/{task_id}", 'destroy')->middleware("role:Employee");
    Route::post("task/{task_id}/upload", 'upload')->middleware("role:Employee");
    Route::get("task/{project_id}/level1/review", 'review')->middleware("role:Supervisor,Manager,Director,ManagerEksekutifS");
    Route::get("task/{project_id}/activities/all", 'taskByProject')->middleware("role:Supervisor,Manager,Director");
    Route::get("task/{employe_id}/recent/activity", 'recentTaskByEmploye')->middleware("role:Staff,Supervisor,Manager");
    Route::post("task/{employe_id}/{task_id}/favorite", 'addFavoriteTask')->middleware("role:Director");
    Route::get("task/director/dashboard/list", 'dashboardList')->middleware("role:Director");
    Route::get("task/director/inprogress/list", 'inProgressList')->middleware("role:Director");
    Route::get("/task/employe/additional/list", 'additionalList')->middleware("role:Staff,Supervisor,Manager");
    Route::delete("/task/file/delete/{file_id}", 'deleteFile')->middleware("role:Staff,Supervisor,Manager");
    Route::post("/task/{taskId}/duplicate", 'duplicateTask')->middleware("role:Employee");

    // 3 LEVEL TASK
    Route::get("/task/{project_id}/employe/list", 'projectTaskByEmploye')->middleware("role:Staff,Supervisor,Manager");
    Route::patch("/task/{task_id}/activity/add-sub", 'addSub')->middleware("role:Staff,Supervisor,Manager");
    Route::patch("/task/{task_id}/activity/update-sub", 'updateSub')->middleware("role:Staff,Supervisor,Manager");
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

// Notification Testing routes
Route::controller(TestingController::class)->group(function(){
    Route::post('/notification/store', "newNotification")->middleware("role:Employee");
});

// Notification routes
Route::controller(NotificationController::class)->group(function(){
    Route::get('/notification', "get")->middleware("role:Employee");
    Route::delete('/notification/{id}', "delete")->middleware("role:Employee");
});

// client routes
Route::controller(ClientController::class)->group(function(){
    Route::get('/client', "index")->middleware("client");
    Route::get('/client/employees', "employees")->middleware("client");
    Route::post('/client/employees/store', "store")->middleware("client");
    Route::get('/client/structure', "stucture")->middleware("client");
});

// check structure
Route::controller(ProjectController::class)->group(function(){
    Route::get('/project/structure/check/exist/is-true', "checkStructure");
});

// report routes
Route::controller(ProjectReportController::class)->group(function(){
    Route::get('/report/all-project', "allProjectToExcel")->middleware("role:Employee");
});

Route::controller(PreviewController::class)->group(function(){
    Route::get('file/preview/{companyId}', 'getFile')->middleware("role:AdminVendorScm,AdminVendorUmum,VendorViewer");
});

// Master Structure router
Route::controller(BoardOrganizationController::class)->group(function(){
    Route::get('/master/insert-code', "insertCode")->middleware("role:Employee");
    Route::get('/master/board/list', "allBoard")->middleware("client");
    Route::post('/master/board/store', "store")->middleware("client");
    Route::put('/master/board/update', "update")->middleware("client");
    Route::delete('/master/board/delete', "delete")->middleware("client");
});

Route::controller(OrganizationController::class)->group(function(){
    Route::get('/master/org/insert-code', "insertCode")->middleware("role:Employee");
    Route::get('/master/org/list', "allOrganization")->middleware("client");
    Route::post('/master/org/store', "store")->middleware("client");
    Route::put('/master/org/update', "update")->middleware("client");
    Route::delete('/master/org/delete', "delete")->middleware("client");
});

Route::controller(PositionController::class)->group(function(){
    Route::get('/master/pos/insert-code', "insertCode")->middleware("role:Employee");
    Route::get('/master/pos/list', "allPosition")->middleware("client");
    Route::post('/master/pos/store', "store")->middleware("client");
    Route::put('/master/pos/update', "update")->middleware("client");
    Route::delete('/master/pos/delete', "delete")->middleware("client");
});

Route::controller(DailyController::class)->group(function(){
    Route::post('/daily/store', "store")->middleware("role:Employee");
});
