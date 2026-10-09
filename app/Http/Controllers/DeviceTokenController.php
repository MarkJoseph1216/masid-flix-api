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
            'fcm_token'   => 'required|string',
            'device_type' => 'nullable|string',
            'device_id'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not authenticated',
                ], 401);
            }

            if ($request->filled('device_id')) {
                $match = [
                    'user_id'   => $user->id,
                    'device_id' => $request->device_id,
                ];
            } else {
                $match = [
                    'user_id'   => $user->id,
                    'fcm_token' => $request->fcm_token,
                ];
            }

            $token = DeviceToken::updateOrCreate(
                $match,
                [
                    'fcm_token'   => $request->fcm_token,
                    'device_type' => $request->device_type ?? 'mobile',
                    'is_active'   => true,
                ]
            );

            $others = DeviceToken::where('user_id', $user->id);
            if ($request->filled('device_id')) {
                $others->where(function ($q) use ($request) {
                    $q->where('device_id', '!=', $request->device_id)
                      ->orWhereNull('device_id');
                });
            } else {
                $others->where('fcm_token', '!=', $request->fcm_token);
            }
            $others->update(['is_active' => false]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Device token registered',
                'data'    => $token,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to register token: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'device_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not authenticated',
                ], 401);
            }

            $query = DeviceToken::where('user_id', $user->id);
            if ($request->filled('device_id')) {
                $query->where('device_id', $request->device_id);
            } else {
                $query->where('fcm_token', $request->fcm_token);
            }

            $deleted = $query->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Device token removed',
                'deleted' => $deleted,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to remove token: ' . $e->getMessage(),
            ], 500);
        }
    }
}