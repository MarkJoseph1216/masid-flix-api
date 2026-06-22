<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class NotificationController extends Controller
{
    private $messaging;

    public function __construct()
    {
        try {
            $factory = $this->getFirebaseFactory();
            $this->messaging = $factory->createMessaging();
            Log::info('Firebase initialized successfully');
        } catch (\Exception $e) {
            Log::error('Firebase init failed: ' . $e->getMessage());
        }
    }

    private function getFirebaseFactory()
    {
        $credentials = env('FIREBASE_SERVICE_ACCOUNT');
        
        if ($credentials && $credentials !== '') {
            Log::info('Using Firebase credentials from environment variable');
            
            $credentialData = json_decode($credentials, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                Log::info('Valid Firebase credentials from env, project: ' . ($credentialData['project_id'] ?? 'unknown'));
                return (new Factory)->withServiceAccount($credentialData);
            } else {
                Log::error('Invalid JSON in FIREBASE_SERVICE_ACCOUNT: ' . json_last_error_msg());
            }
        }
        
        $filePath = storage_path('app/firebase/firebase-credentials.json');
        Log::info('📁 Looking for Firebase credentials at: ' . $filePath);
        
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return (new Factory)->withServiceAccount($filePath);
            }
        }
        
        Log::error('No valid Firebase credentials found');
        return null;
    }

    public function handle(Request $request)
    {
        try {
            Log::info('Notification webhook received');
            Log::info('Request data: ' . json_encode($request->all()));

            $validated = $request->validate([
                'message_id' => 'required|integer',
                'type' => 'required|string',
                'sender_name' => 'required|string',
                'message' => 'required|string',
                'sender_id' => 'required|integer',
                'recipient_id' => 'required|integer',
                'chat_type' => 'required|in:direct,party',
                'fcm_token' => 'required|string',
            ]);

            if (!$this->messaging) {
                Log::error('Firebase messaging not initialized');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Firebase not initialized'
                ], 500);
            }

            $this->sendFCMNotification($validated);

            Log::info('FCM sent successfully');
            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Notification error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function sendFCMNotification(array $data)
    {
        try {
            if ($data['chat_type'] === 'direct') {
                $title = "New message from {$data['sender_name']}";
            } else {
                $title = "Party chat from {$data['sender_name']}";
            }

            Log::info('Building notification: ' . $title);
            $message = CloudMessage::new()
                ->withToken($data['fcm_token'])
                ->withNotification(FirebaseNotification::create($title, $data['message']))
                ->withData([
                    'sender_id' => (string) $data['sender_id'],
                    'sender_name' => $data['sender_name'],
                    'message_id' => (string) $data['message_id'],
                    'type' => $data['type'],
                    'chat_type' => $data['chat_type'],
                ]);

            Log::info('ending FCM message...');
            $this->messaging->send($message);
            Log::info('FCM message sent successfully');

        } catch (\Exception $e) {
            Log::error('FCM send error: ' . $e->getMessage());
            
            if (str_contains($e->getMessage(), 'registration token') ||
                str_contains($e->getMessage(), 'Invalid token') ||
                str_contains($e->getMessage(), 'NotRegistered')) {
                DeviceToken::where('fcm_token', $data['fcm_token']) ->update(['is_active' => false]);
                Log::warning('⚠️ Token deactivated');
            }
            throw $e;
        }
    }
}