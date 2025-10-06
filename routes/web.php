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
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Http\Controllers\PasswordSetupController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Http\Request;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\HelpChatController;



//locale
Route::post('/locale', [LocaleController::class, 'set'])->name('locale.set');

//cookie-banner
Route::prefix('cookie-consent')->name('cookie-consent.')->group(function () {
    Route::post('/store', [CookieConsentController::class, 'store'])->name('store');
    Route::post('/accept-all', [CookieConsentController::class, 'acceptAll'])->name('accept-all');
    Route::post('/accept-necessary', [CookieConsentController::class, 'acceptNecessary'])->name('accept-necessary');
    Route::get('/status', [CookieConsentController::class, 'status'])->name('status');
    Route::get('/policy', [CookieConsentController::class, 'policy'])->name('policy');
    Route::get('/preferences', [CookieConsentController::class, 'preferences'])->name('preferences');
});

Route::controller(HelpChatController::class)
    ->prefix('/help')
    ->middleware(['auth:'.UserType::NORMAL])
    ->name('help.')
    ->group(function(){
        Route::get('/content', 'getHelpContent')->name('content');
        Route::post('/chat/send', 'sendMessage')->name('chat.send')->middleware('throttle:30,1');
        Route::get('/chat/sessions', 'listSessions')->name('chat.sessions');
        Route::get('/chat/session/{sessionId}', 'loadSession')->name('chat.session');
        Route::post('/chat/session/new', 'createSession')->name('chat.session.new');
        Route::delete('/chat/session/{sessionId}', 'deleteSession')->name('chat.session.delete');
    });

// login routes
Route::controller(LoginController::class)->middleware('auth:'.UserType::GUEST)->group(function () {
    Route::get('/{login?}', 'index')->where('login','|login')->name('login');
    Route::get('/trigger-login', 'triggerLogin')->name('trigger-login');
    Route::any('/attempt-login', 'attemptLogin')->name('attempt-login');
    // Microsoft login
    Route::get('/trigger-microsoft-login', [LoginController::class, 'triggerMicrosoftLogin'])->name('trigger-microsoft-login');
    Route::get('/auth/microsoft/callback', [LoginController::class, 'attemptMicrosoftLogin'])->name('microsoft.callback');

    Route::post('/attempt-password-login', 'passwordLogin')
        ->name('attempt-password-login')
        ->middleware('throttle:5,1');

    Route::post('/verify-2fa-code', 'verify2faCode')
        ->name('verify-2fa-code')
        ->middleware('throttle:10,1');
    
    Route::post('/resend-2fa-code', 'resend2faCode')
        ->name('resend-2fa-code')
        ->middleware('throttle:5,1');
});

// Password setup (guest)
Route::controller(PasswordSetupController::class)
    ->middleware('auth:'.\App\Models\Enums\UserType::GUEST)
    ->group(function () {
        Route::get('/password-setup/{token}', 'show')->name('password-setup.show');
        Route::post('/password-setup/{token}', 'store')->name('password-setup.store');
    });

// logout
Route::controller(LoginController::class)->middleware('auth:'.UserType::NORMAL)->group(function () {
    Route::get('/logout', 'logout')->name('logout');
});

// OPEN REGISTRATION ROUTES
Route::controller(RegistrationController::class)
    ->middleware('auth:' . \App\Models\Enums\UserType::GUEST)
    ->group(function () {
        Route::get('/register', 'show')->name('register.show');
        Route::post('/register', 'register')->name('register.perform');
        Route::post('/register/validate-step', 'validateStepAjax')->name('register.validate-step');
    });

// home redirect
Route::get('/home-redirect', function(Request $request){
    $target = Auth::isAuthorized(UserType::ADMIN) ? 'admin.home' : 'home';
    session()->reflash();
    return redirect()->route($target);
})->name('home-redirect')->middleware('auth:'.UserType::NORMAL);

// webhook (outside of auth) - PROTECTED BY IP WHITELIST
Route::match(['POST'], '/webhook/barion', [PaymentWebhookController::class, 'barion'])
    ->name('webhook.barion')
    ->middleware(['barion.webhook.ip', 'throttle:webhook']);
    
// ADMIN ROUTES
Route::prefix('/admin')->name('admin.')->middleware(['auth:'.UserType::ADMIN, 'org', 'check.initial.payment'])->group(function(){
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
        Route::post('/password-reset', 'passwordReset')->name('password-reset');
        Route::post('/department', 'storeDepartment')->name('department.store');
        Route::post('/department/get', 'getDepartment')->name('department.get');
        Route::post('/department/update', 'updateDepartment')->name('department.update');
        Route::post('/department/members', 'getDepartmentMembers')->name('department.members');
        Route::post('/department/eligible', 'getEligibleForDepartment')->name('department.eligible');
        Route::post('/department/members/save', 'saveDepartmentMembers')->name('department.members.save');
        Route::post('/department/delete', 'deleteDepartment')->name('department.delete');
        Route::post('/network', 'getNetworkData')->name('network');
        Route::post('/get-eligible-managers', [AdminEmployeeController::class, 'getEligibleManagers'])->name('get-eligible-managers');
    });

    // payments
    Route::controller(AdminPaymentController::class)->name('payments.')->prefix('/payments')->group(function () {
        Route::get('/index', 'index')->name('index');
        Route::post('/start', 'start')->name('start');
        Route::get('/invoice/{id}', 'invoice')->name('invoice');
        Route::post('/refresh', 'refresh')->name('refresh');
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
        Route::post('/question/translations/get', 'getCompetencyQuestionTranslations')->name('question.translations.get');
        Route::post('/translations/get', 'getCompetencyTranslations')->name('translations.get');
        Route::post('/translate-name', 'translateCompetencyName')->name('translate-name');
        Route::post('/translate-question', 'translateCompetencyQuestion')->name('translate-question');
    });

    Route::controller(AdminCompetencyController::class)->name('competency-group.')->prefix('/competency-group')->group(function () {
        Route::post('/save', 'saveCompetencyGroup')->name('save');
        Route::post('/get', 'getCompetencyGroup')->name('get');
        Route::post('/remove', 'removeCompetencyGroup')->name('remove');
        Route::post('/all', 'getAllCompetencyGroups')->name('all');
        Route::post('/users/get', 'getCompetencyGroupUsers')->name('users.get');
        Route::post('/users/save', 'saveCompetencyGroupUsers')->name('users.save');
        Route::post('/users/eligible', 'getEligibleUsersForGroup')->name('users.eligible');
    });

    Route::controller(AdminCompetencyController::class)->name('languages.')->prefix('/languages')->group(function () {
        Route::get('/available', 'getAvailableLanguages')->name('available');
        Route::get('/selected', 'getSelectedLanguages')->name('selected');
        Route::post('/selected', 'getSelectedLanguages')->name('selected');
        Route::post('/save', 'saveTranslationLanguages')->name('save');
    });

    // ceoranks
    Route::controller(AdminCeoRanksController::class)->name('ceoranks.')->prefix('/ceoranks')->group(function () {
        Route::get('/index', 'index')->name('index');
        Route::post('/get', 'getCeoRank')->name('get');
        Route::post('/save', 'saveCeoRank')->name('save');
        Route::post('/remove', 'removeCeoRank')->name('remove');
        Route::post('/translations/get', 'getCeoRankTranslations')->name('translations.get');
    Route::get('/languages/available', 'getAvailableLanguages')->name('languages.available');
    Route::get('/languages/selected', 'getSelectedLanguages')->name('languages.selected');
    Route::post('/languages/save', 'saveTranslationLanguages')->name('languages.save');
    Route::post('/translate-name', 'translateCeoRankName')->name('translate-name');

    });

    // results
    Route::get('/results/{assessmentId?}', [AdminResultsController::class, 'index'])->name('results.index');

    // settings (admin)
    Route::controller(\App\Http\Controllers\AdminSettingsController::class)
        ->name('settings.')
        ->prefix('/settings')
        ->group(function () {
            Route::get('/index', 'index')->name('index');
            Route::post('/toggle', 'toggle')->name('toggle');
            Route::post('/thresholds', 'saveThresholds')->name('save');
        });
});

// NORMAL USER ROUTES (includes CEO and Manager access via middleware)
Route::middleware(['auth:'.UserType::NORMAL, 'org'])->group(function () {
    Route::get('/home', [HomeController::class, 'normal'])->name('home');

    // assessment
    Route::controller(AssessmentController::class)->name('assessment.')->prefix('/assessment')->group(function () {
        Route::get('/{targetId?}', 'index')->name('index');
        Route::post('/submit', 'submitAssessment')->name('submit');
    });

    // results (NORMAL)
    Route::get('/results/{assessmentId?}', [ResultsController::class, 'index'])->name('results.index');
});

// CEO + MANAGER RANKS (egységes route és név; jogosultságot a controller intézi)
Route::controller(CeoRankController::class)
    ->name('ceorank.')
    ->prefix('/ceorank')
    ->middleware(['auth:' . UserType::NORMAL, 'org']) // bármely bejelentkezett user
    ->group(function () {
        Route::get('/index', 'index')->name('index');
        Route::post('/submit', 'submitRanking')->name('submit');
    });

// dev routes
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

// SUPERADMIN ROUTES
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

// SUPERADMIN COMPETENCY ROUTES - FIXED: Added proper route aliases for JavaScript compatibility
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
        Route::post('/translations/get', 'getCompetencyTranslations')->name('translations.get');
        Route::post('/question/translations/get', 'getCompetencyQuestionTranslations')->name('question.translations.get');
        Route::get('/languages/available', 'getAvailableLanguages')->name('languages.available');
        Route::get('/languages/selected', 'getSelectedLanguages')->name('languages.selected');
        Route::post('/translate-name', 'translateCompetencyName')->name('translate-name');
        Route::post('/translate-question', 'translateCompetencyQuestion')->name('translate-question');
        
        // FIXED: Add aliases for JavaScript compatibility
        Route::post('/question/remove', 'removeCompetencyQuestion')->name('q.remove'); // Alias for JS
        Route::post('/question/save', 'saveCompetencyQuestion')->name('q.save');      // Alias for JS  
        Route::get('/question/get', 'getCompetencyQuestion')->name('q.get');          // Alias for JS
    });
    
Route::get('/superadmin/global-competencies', [GlobalCompetencyController::class, 'index'])->name('superadmin.global-competencies');

// Flash message route
Route::post('/flash-success', function (\Illuminate\Http\Request $request) {
    session()->flash('success', $request->input('message'));
    return response()->json(['ok' => true]);
})->name('flash.success');