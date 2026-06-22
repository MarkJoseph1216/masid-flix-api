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

Route::get('/test-firebase', function () {
    try {
        $credentials = env('FIREBASE_SERVICE_ACCOUNT');
        
        if (!$credentials) {
            return response()->json([
                'status' => 'error',
                'message' => 'FIREBASE_SERVICE_ACCOUNT env variable not set'
            ], 500);
        }
        
        $data = json_decode($credentials, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid JSON: ' . json_last_error_msg()
            ], 500);
        }
        
        $factory = (new Kreait\Firebase\Factory)->withServiceAccount($data);
        $messaging = $factory->createMessaging();
        
        return response()->json([
            'status' => 'success',
            'project_id' => $data['project_id'] ?? 'unknown',
            'client_email' => $data['client_email'] ?? 'unknown'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/debug-firebase', function () {
    try {
        $credentialsJson = env('FIREBASE_SERVICE_ACCOUNT');
        
        $result = [
            'env_exists' => $credentialsJson !== null,
            'env_length' => strlen($credentialsJson ?? ''),
            'json_valid' => false,
            'project_id' => null,
            'client_email' => null,
            'has_private_key' => false,
            'openssl_available' => extension_loaded('openssl'),
        ];
        
        if ($credentialsJson) {
            $data = json_decode($credentialsJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result['json_valid'] = true;
                $result['project_id'] = $data['project_id'] ?? null;
                $result['client_email'] = $data['client_email'] ?? null;
                $result['has_private_key'] = isset($data['private_key']);
                
                if (isset($data['private_key'])) {
                    $key = str_replace('\n', "\n", $data['private_key']);
                    $keyResource = openssl_pkey_get_private($key);
                    $result['private_key_valid'] = $keyResource !== false;
                    if ($keyResource) {
                        openssl_pkey_free($keyResource);
                    }
                }
            } else {
                $result['json_error'] = json_last_error_msg();
            }
        }
        
        return response()->json($result);
        
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
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