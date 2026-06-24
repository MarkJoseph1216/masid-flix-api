<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated',
                    'debug' => 'No user found in request'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string',
                'device_type' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tableExists = Schema::hasTable('device_tokens');
            
            if (!$tableExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'device_tokens table does not exist',
                    'debug' => 'Please run migration to create device_tokens table'
                ], 500);
            }

            try {
                $existingToken = DeviceToken::where('user_id', $user->id)
                    ->where('fcm_token', $request->fcm_token)
                    ->first();

                if ($existingToken) {
                    $existingToken->update([
                        'device_type' => $request->device_type ?? 'mobile',
                        'is_active' => true,
                    ]);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Device token updated',
                        'data' => $existingToken
                    ]);
                }

                $token = DeviceToken::create([
                    'user_id' => $user->id,
                    'fcm_token' => $request->fcm_token,
                    'device_type' => $request->device_type ?? 'mobile',
                    'is_active' => true,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Device token registered',
                    'data' => $token
                ]);

            } catch (\Exception $dbError) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Database error: ' . $dbError->getMessage(),
                    'debug' => [
                        'user_id' => $user->id,
                        'fcm_token' => $request->fcm_token,
                        'error_line' => $dbError->getLine(),
                        'error_file' => $dbError->getFile(),
                    ]
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $deleted = DeviceToken::where('user_id', $user->id)
                ->where('fcm_token', $request->fcm_token)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Device token removed',
                'deleted' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove token: ' . $e->getMessage(),
            ], 500);
        }
    }
}