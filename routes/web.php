<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SSO\AuthController;
use App\Http\Controllers\SSO\OAuthController;
use App\Http\Controllers\SSO\MagicLinkController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\SecurityController;

// Public routes
Route::get('/', function () {
    return view('sso-welcome');
});

// Laravel Auth Route (required for auth middleware)
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

// Test route
Route::get('/test-auth', function() {
    return [
        'authenticated' => Auth::check(),
        'user' => Auth::user(),
        'id' => Auth::id()
    ];
});

// SSO Authentication Routes
Route::prefix('auth')->name('sso.')->middleware(['auth-rate-limit'])->group(function () {
    // Login/Register
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Email Verification
    Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('verify-email');
    
    // MFA
    Route::get('/mfa', [AuthController::class, 'showMfaForm'])->name('mfa');
    Route::post('/mfa', [AuthController::class, 'verifyMfa'])->name('mfa.verify');
    
    // Magic Links
    Route::get('/magic', [MagicLinkController::class, 'showRequestForm'])->name('magic');
    Route::post('/magic', [MagicLinkController::class, 'sendMagicLink'])->name('magic.send');
    Route::get('/magic/verify/{token}', [MagicLinkController::class, 'verify'])->name('magic.verify');

    // Social Login
    Route::get('/social/{provider}', [SocialLoginController::class, 'redirect'])->name('social');
    Route::get('/social/{provider}/callback', [SocialLoginController::class, 'callback'])->name('social.callback');
});

// OAuth2/OIDC Routes
Route::prefix('oauth')->name('oauth.')->middleware(['oauth-rate-limit'])->group(function () {
    // Authorization
    Route::get('/authorize', [OAuthController::class, 'authorize'])->name('authorize');
    Route::post('/authorize', [OAuthController::class, 'handleAuthorization'])->name('authorize.handle');
    
    // Token endpoints
    Route::post('/token', [OAuthController::class, 'token'])->name('token');
    Route::post('/revoke', [OAuthController::class, 'revoke'])->name('revoke');
    
    // OIDC Discovery
    Route::get('/.well-known/openid-configuration', [OAuthController::class, 'discovery'])->name('discovery');
    Route::get('/.well-known/jwks.json', [OAuthController::class, 'jwks'])->name('jwks');
    
    // User info
    Route::get('/userinfo', [OAuthController::class, 'userinfo'])->middleware('auth:api')->name('userinfo');
});

// SPA Route
Route::get('/admin', function () {
    return view('spa');
})->middleware('auth')->name('admin');

// Admin HTMX Routes (protected)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/groups', [AdminController::class, 'groups'])->name('groups');
    Route::get('/domains', [AdminController::class, 'domains'])->name('domains');
    Route::get('/permissions', [AdminController::class, 'permissions'])->name('permissions');
    
    // User Management
    Route::get('/users/create', [AdminController::class, 'createUser'])->name('users.create');
    Route::get('/users/{id}/edit', [AdminController::class, 'editUser'])->name('users.edit');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::put('/users/{id}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->name('users.delete');
    
    // Domain Management
    Route::get('/domains/create', [AdminController::class, 'createDomain'])->name('domains.create');
    Route::get('/domains/{id}/edit', [AdminController::class, 'editDomain'])->name('domains.edit');
    Route::post('/domains', [AdminController::class, 'storeDomain'])->name('domains.store');
    Route::put('/domains/{id}', [AdminController::class, 'updateDomain'])->name('domains.update');
    Route::delete('/domains/{id}', [AdminController::class, 'deleteDomain'])->name('domains.delete');
    
    // Permission Sync
    Route::post('/permissions/sync', [AdminController::class, 'syncDomainPermissions'])->name('permissions.sync');
    Route::post('/permissions/sync-all', [AdminController::class, 'syncAllPermissions'])->name('permissions.sync-all');

    // Security Dashboard
    Route::get('/security', [SecurityController::class, 'dashboard'])->name('security');
    Route::get('/security/user-patterns/{userId}', [SecurityController::class, 'userPatterns'])->name('security.user-patterns');
});

// 2FA Routes
Route::middleware(['auth', 'rate.limit:2fa'])->prefix('2fa')->name('2fa.')->group(function () {
    Route::get('/setup', [App\Http\Controllers\TwoFactorController::class, 'setup'])->name('setup');
    Route::post('/enable', [App\Http\Controllers\TwoFactorController::class, 'enable'])->name('enable');
    Route::get('/manage', [App\Http\Controllers\TwoFactorController::class, 'manage'])->name('manage');
    Route::post('/disable', [App\Http\Controllers\TwoFactorController::class, 'disable'])->name('disable');
    Route::post('/regenerate-recovery', [App\Http\Controllers\TwoFactorController::class, 'regenerateRecoveryCodes'])->name('regenerate-recovery');
    Route::get('/recovery-codes', [App\Http\Controllers\TwoFactorController::class, 'showRecoveryCodes'])->name('recovery-codes');
});

// API Routes
Route::middleware(['auth'])->prefix('api/admin')->name('api.admin.')->group(function () {
    Route::get('/me', [App\Http\Controllers\Api\AdminController::class, 'me']);
    Route::get('/dashboard', [App\Http\Controllers\Api\AdminController::class, 'dashboard']);
    
    // Stats
    Route::get('/stats/users', function() {
        return App\Models\User::count();
    });
    Route::get('/stats/groups', function() {
        return App\Models\Group::count();
    });
    Route::get('/stats/domains', function() {
        return App\Models\Domain::count();
    });
    Route::get('/stats/sessions', function() {
        return DB::table('user_sessions')->where('expires_at', '>', now())->count();
    });
    
    // Users
    Route::get('/users', [App\Http\Controllers\Api\AdminController::class, 'users']);
    Route::get('/users/list', [App\Http\Controllers\Api\AdminController::class, 'users']);
    Route::get('/users/search', [App\Http\Controllers\Api\AdminController::class, 'users']);
    Route::get('/users/{id}', [App\Http\Controllers\Api\AdminController::class, 'getUser']);
    Route::post('/users', [App\Http\Controllers\Api\AdminController::class, 'createUser']);
    Route::put('/users/{id}', [App\Http\Controllers\Api\AdminController::class, 'updateUser']);
    Route::delete('/users/{id}', [App\Http\Controllers\Api\AdminController::class, 'deleteUser']);
    
    // Groups
    Route::get('/groups', [App\Http\Controllers\Api\AdminController::class, 'groups']);
    Route::get('/groups/select', function() {
        $groups = App\Models\Group::all(['id', 'name', 'slug']);
        $html = '';
        foreach($groups as $group) {
            $html .= '<option value="'.$group->id.'">'.$group->name.'</option>';
        }
        return $html;
    });
    Route::get('/groups/{id}', [App\Http\Controllers\Api\AdminController::class, 'getGroup']);
    Route::post('/groups', [App\Http\Controllers\Api\AdminController::class, 'createGroup']);
    Route::put('/groups/{id}', [App\Http\Controllers\Api\AdminController::class, 'updateGroup']);
    Route::delete('/groups/{id}', [App\Http\Controllers\Api\AdminController::class, 'deleteGroup']);
    Route::post('/groups/{id}/move', [App\Http\Controllers\Api\AdminController::class, 'moveGroup']);
    
    // Domains & Permissions
    Route::get('/domains', [App\Http\Controllers\Api\AdminController::class, 'domains']);
    Route::get('/permissions', [App\Http\Controllers\Api\AdminController::class, 'permissions']);
    
    // Recent logins
    Route::get('/recent-logins', function() {
        $logins = App\Models\User::orderBy('last_login_at', 'desc')
            ->whereNotNull('last_login_at')
            ->take(10)
            ->get(['name', 'last_login_at', 'last_login_ip']);
        
        $html = '<table class="table"><thead><tr><th>Name</th><th>Zeitpunkt</th><th>IP</th></tr></thead><tbody>';
        foreach($logins as $login) {
            $html .= '<tr>';
            $html .= '<td>'.$login->name.'</td>';
            $html .= '<td>'.($login->last_login_at ? $login->last_login_at->diffForHumans() : '-').'</td>';
            $html .= '<td>'.$login->last_login_ip.'</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    });

    // Security API Routes
    Route::get('/security/stats', [SecurityController::class, 'stats']);
    Route::get('/security/events', [SecurityController::class, 'events']);
    Route::get('/security/check-ip', [SecurityController::class, 'checkIp']);
});
