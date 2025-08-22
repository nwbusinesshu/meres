<?php

use App\Http\Controllers\AdminAssessmentController;
use App\Http\Controllers\AdminCeoRanksController;
use App\Http\Controllers\AdminCompetencyController;
use App\Http\Controllers\AdminEmployeeController;
use App\Http\Controllers\AdminResultsController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\CeoRankController;
use App\Http\Controllers\DevController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ResultsController;
use App\Http\Middleware\Auth;
use App\Models\Enums\UserType;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperadminOrganizationController;
use App\Http\Controllers\GlobalCompetencyController;


// login
Route::controller(LoginController::class)->middleware('auth:'.UserType::GUEST)->group(function () {
    Route::get('/{login?}', 'index')->where('login','|login')->name('login');
    Route::get('/trigger-login', 'triggerLogin')->name('trigger-login');
    Route::any('/attempt-login', 'attemptLogin')->name('attempt-login');
});

Route::controller(LoginController::class)->middleware('auth:'.UserType::NORMAL)->group(function () {
    Route::get('/logout', 'logout')->name('logout');
});

// home redirect
Route::get('/home-redirect', function(Request $request){
    $target = Auth::isAuthorized(UserType::ADMIN) ? 'admin.home' : 'home';
    session()->reflash();
    return redirect()->route($target);
})->name('home-redirect')->middleware('auth:'.UserType::NORMAL);

// admin
Route::prefix('/admin')->name('admin.')->middleware(['auth:'.UserType::ADMIN, 'org'])->group(function(){
    Route::get('/home', [HomeController::class, 'admin'])->name('home');

    // assessment
    Route::controller(AdminAssessmentController::class)->name('assessment.')->prefix('/assessment')->group(function () {
        Route::post('/get', 'getAssessment')->name('get');
        Route::post('/save', 'saveAssessment')->name('save');
        Route::post('/close', 'closeAssessment')->name('close');
    });

    // employee
    Route::controller(AdminEmployeeController::class)->name('employee.')->prefix('/employee')->group(function () {
        Route::get('/index', 'index')->name('index');
        Route::post('/get', 'getEmployee')->name('get');
        Route::post('/save', 'saveEmployee')->name('save');
        Route::post('/remove', 'removeEmployee')->name('remove');
        Route::post('/all', 'getAllEmployee')->name('all');
        Route::post('/relations', 'getEmployeeRelations')->name('relations');
        Route::post('/relations/save', 'saveEmployeeRelations')->name('relations.save');
        Route::post('/competencies', 'getEmployeeCompetencies')->name('competencies');
        Route::post('/competencies/save', 'saveEmployeeCompetencies')->name('competencies.save');
        Route::post('/bonusmalus/get', 'getBonusMalus')->name('bonusmalus.get');
        Route::post('/bonusmalus/set', 'setBonusMalus')->name('bonusmalus.set');
    });

    // competency
    Route::controller(AdminCompetencyController::class)->name('competency.')->prefix('/competency')->group(function () {
        Route::get('/index', 'index')->name('index');
        Route::post('/all', 'getAllCompetency')->name('all');
        Route::post('/save', 'saveCompetency')->name('save');
        Route::post('/remove', 'removeCompetency')->name('remove');
        Route::post('/question/save', 'saveCompetencyQuestion')->name('question.save');
        Route::post('/question/get', 'getCompetencyQuestion')->name('question.get');
        Route::post('/question/remove', 'removeCompetencyQuestion')->name('question.remove');
    });

    // ceoranks
    Route::controller(AdminCeoRanksController::class)->name('ceoranks.')->prefix('/ceoranks')->group(function () {
        Route::get('/index', 'index')->name('index');
        Route::post('/get', 'getCeoRank')->name('get');
        Route::post('/save', 'saveCeoRank')->name('save');
        Route::post('/remove', 'removeCeoRank')->name('remove');
    });

    // results
    Route::controller(AdminResultsController::class)->name('results.')->prefix('/results')->group(function () {
        Route::get('/index', 'index')->name('index');
    });
});

// normal
Route::controller(NormalController::class)->middleware(['auth:'.UserType::NORMAL, 'org'])->group(function () {
    Route::get('/home', [HomeController::class,'normal'])->name('home');

    // assessment
    Route::controller(AssessmentController::class)->name('assessment.')->prefix('/assessment')->group(function () {
        Route::get('/{targetId?}', 'index')->name('index');
        Route::post('/submit', 'submitAssessment')->name('submit');
    });

    // ceorank
    Route::controller(CeoRankController::class)->name('ceorank.')->prefix('/ceorank')->middleware('auth:'.UserType::CEO)->group(function () {
        Route::get('/index', 'index')->name('index');
        Route::post('/submit', 'submitRanking')->name('submit');
    });

    // results
    Route::controller(ResultsController::class)->name('results.')->prefix('/results')->group(function () {
        Route::get('/index', 'index')->name('index');
    });
});

// dev
Route::controller(DevController::class)->name('dev.')->prefix('/dev')->group(function(){
    Route::get('/makeFullAssessment', 'makeFullAssessment')->name('makeFullAssessment');
    Route::get('/generateBonusMalus', 'generateBonusMalus')->name('generateBonusMalus');
});

// org-selector
Route::controller(OrganizationController::class)
    ->prefix('/org')
    ->middleware('auth:'.UserType::NORMAL)
    ->name('org.')
    ->group(function(){
        Route::get('/select', 'select')->name('select');
        Route::post('/switch', 'switch')->name('switch');
    });

// superadmin
Route::middleware(['auth:' . UserType::SUPERADMIN, 'org'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->controller(SuperAdminController::class)
    ->group(function () {
        Route::get('/dashboard', 'dashboard')->name('dashboard');
        Route::post('/org/store', 'store')->name('org.store');
        Route::post('/org/update', 'update')->name('org.update');
        Route::post('/org/delete', 'delete')->name('org.delete');
    });

Route::prefix('/superadmin')->name('superadmin.')->middleware(['auth:' . UserType::SUPERADMIN])->group(function () {
        Route::get('/org/create', [SuperadminOrganizationController::class, 'create'])->name('org.create');
        Route::post('/organization/store', [SuperadminOrganizationController::class, 'store'])->name('organization.store');
    });

Route::get('/superadmin/exit-company', [SuperAdminController::class, 'exitCompany'])->name('superadmin.exit-company');
    Route::get('/superadmin/org/{id}/data', [SuperAdminController::class, 'getOrgData'])->name('superadmin.org.data');

Route::prefix('superadmin/competency')
    ->name('superadmin.competency.')
    ->middleware(['auth:' . UserType::SUPERADMIN])
    ->controller(GlobalCompetencyController::class)
    ->group(function () {
        Route::get('/index', 'index')->name('index');

        Route::post('/save', 'saveCompetency')->name('save');
        Route::post('/remove', 'removeCompetency')->name('remove');

        Route::get('/question/get', 'getCompetencyQuestion')->name('question.get');
        Route::post('/question/save', 'saveCompetencyQuestion')->name('question.save');
        Route::post('/question/remove', 'removeCompetencyQuestion')->name('question.remove');
    });
