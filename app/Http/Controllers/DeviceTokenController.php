<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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
            DeviceToken::where('user_id', $request->user()->id)
                ->where('fcm_token', $request->fcm_token)
                ->update(['is_active' => false]);

            $token = DeviceToken::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'fcm_token' => $request->fcm_token,
                ],
                [
                    'device_type' => $request->device_type,
                    'is_active' => true,
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Device token registered',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to register token',
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
            DeviceToken::where('user_id', $request->user()->id)
                ->where('fcm_token', $request->fcm_token)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Device token removed',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove token',
            ], 500);
        }
    }
}