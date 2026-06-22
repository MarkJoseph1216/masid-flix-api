<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\WatchRoomController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DeviceTokenController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/notification', [NotificationController::class, 'handle'])
    ->middleware('throttle:100,1');

Route::get('/ping', function () {
    return response()->json(['status' => 'alive', 'time' => now()]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    Route::get('/online-friends', [AuthController::class, 'onlineFriends']);
    Route::get('/users', [AuthController::class, 'getUsers']);
    Route::post('/heartbeat', [AuthController::class, 'heartbeat']);

    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount']);
    Route::get('/messages/conversations', [MessageController::class, 'conversations']);
    Route::post('/messages/mark-read', [MessageController::class, 'markAsRead']);

    Route::post('/watch-room/create', [WatchRoomController::class, 'create']);
    Route::post('/watch-room/join', [WatchRoomController::class, 'join']);
    Route::post('/watch-room/leave', [WatchRoomController::class, 'leave']);
    Route::post('/watch-room/sync', [WatchRoomController::class, 'syncPlayback']);
    Route::get('/watch-room/details', [WatchRoomController::class, 'getRoom']);
    Route::get('/watch-room/active', [WatchRoomController::class, 'getActiveRooms']);

    Route::post('/device-token', [DeviceTokenController::class, 'store']);

    Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);
});