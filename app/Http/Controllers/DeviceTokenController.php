<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $token = DeviceToken::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'fcm_token' => $request->fcm_token,
                ],
                [
                    'device_type' => $request->device_type ?? 'mobile',
                    'is_active' => true,
                ]
            );

            DeviceToken::where('user_id', $user->id)
                ->where('fcm_token', '!=', $request->fcm_token)
                ->update(['is_active' => false]);

            return response()->json([
                'status' => 'success',
                'message' => 'Device token registered',
                'data' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to register token: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
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