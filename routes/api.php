<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\Admin\AdminReportController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConnectionController;
use App\Http\Controllers\Api\ConnectionRequestController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\NearbyController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QrController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Authenticated (mobile bearer tokens via Sanctum)
|--------------------------------------------------------------------------
| `active` blocks suspended and banned accounts that still hold a valid token.
*/

Route::middleware(['auth:sanctum', 'active'])->group(function () {

    // -------------------------------------------------------------------- auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // ----------------------------------------------------------------- profile
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::post('profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::get('profile/completion', [ProfileController::class, 'completion']);
    Route::put('profile/skills', [ProfileController::class, 'updateSkills']);
    Route::put('profile/interests', [ProfileController::class, 'updateInterests']);
    Route::put('profile/privacy', [ProfileController::class, 'updatePrivacy']);

    // Reference data the onboarding screens need to render their pickers.
    Route::get('skills', [ReferenceDataController::class, 'skills']);
    Route::get('interests', [ReferenceDataController::class, 'interests']);

    // ------------------------------------------------------- nearby / discovery
    Route::put('nearby/discoverability', [NearbyController::class, 'updateDiscoverability']);
    Route::get('nearby', [NearbyController::class, 'index']);

    // ------------------------------------------------------------------- users
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::post('users/{user}/connect', [UserController::class, 'connect']);
    Route::post('users/{user}/block', [UserController::class, 'block']);
    Route::delete('users/{user}/block', [UserController::class, 'unblock']);
    Route::post('users/{user}/report', [UserController::class, 'report']);

    // ------------------------------------------------------------- connections
    // Declared before the /connections/{connection} routes so "requests" is not
    // swallowed by the model-bound parameter.
    Route::get('connections/requests', [ConnectionRequestController::class, 'index']);
    Route::post('connections/requests/{connectionRequest}/accept', [ConnectionRequestController::class, 'accept']);
    Route::post('connections/requests/{connectionRequest}/decline', [ConnectionRequestController::class, 'decline']);

    Route::get('connections', [ConnectionController::class, 'index']);
    Route::get('connections/{connection}', [ConnectionController::class, 'show']);
    Route::delete('connections/{connection}', [ConnectionController::class, 'destroy']);
    Route::put('connections/{connection}/favorite', [ConnectionController::class, 'favorite']);
    Route::put('connections/{connection}/note', [ConnectionController::class, 'note']);

    // ------------------------------------------------------------------ events
    Route::get('events', [EventController::class, 'index']);
    Route::post('events', [EventController::class, 'store']);
    Route::get('events/{event}', [EventController::class, 'show']);
    Route::put('events/{event}', [EventController::class, 'update']);
    Route::delete('events/{event}', [EventController::class, 'destroy']);
    Route::post('events/{event}/join', [EventController::class, 'join']);
    Route::post('events/{event}/leave', [EventController::class, 'leave']);
    Route::get('events/{event}/attendees', [EventController::class, 'attendees']);
    Route::get('events/{event}/networking', [EventController::class, 'networking']);

    // ---------------------------------------------------------------------- qr
    Route::get('qr/me', [QrController::class, 'me']);
    Route::post('qr/rotate', [QrController::class, 'rotate']);
    Route::post('qr/scan', [QrController::class, 'scan']);

    // ------------------------------------------------------------------ search
    Route::get('search', SearchController::class);

    // ----------------------------------------------------------- notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // --------------------------------------------------------------- devices
    // Registers this install for FCM push. See config/mingle.php and the
    // README for turning real delivery on.
    Route::post('devices', [DeviceController::class, 'store']);
    Route::post('devices/unregister', [DeviceController::class, 'destroy']);

    // ----------------------------------------------------------------- account
    Route::delete('account', [AccountController::class, 'destroy']);

    /*
    |----------------------------------------------------------------------
    | Admin (web dashboard surface, not used by the mobile app)
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('can:access-admin')->group(function () {
        Route::get('users', [AdminUserController::class, 'index']);
        Route::put('users/{user}/verify', [AdminUserController::class, 'verify']);
        Route::put('users/{user}/suspend', [AdminUserController::class, 'suspend']);
        Route::put('users/{user}/ban', [AdminUserController::class, 'ban']);

        Route::get('reports', [AdminReportController::class, 'index']);
        Route::put('reports/{report}', [AdminReportController::class, 'update']);

        Route::get('analytics/summary', [AdminAnalyticsController::class, 'summary']);
    });
});
