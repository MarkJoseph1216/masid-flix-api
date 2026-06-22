<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationController extends Controller
{
    private $messaging;

    public function __construct()
    {
        try {
            $factory = (new Factory)
                ->withServiceAccount(storage_path('app/firebase/firebase-credentials.json'));
            $this->messaging = $factory->createMessaging();
            Log::info('Firebase initialized successfully');
        } catch (\Exception $e) {
            Log::error('Firebase init failed: ' . $e->getMessage());
        }
    }

    public function handle(Request $request)
    {
        $debug = ['steps' => [], 'error' => null, 'success' => false];

        try {
            Log::info('Notification webhook received');
            
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

            $debug['steps'][] = 'Request validated';

            $result = $this->sendFCMNotification($validated, $debug);
            $debug['success'] = true;

            return response()->json([
                'status' => 'success',
                'result' => $result,
                'debug' => $debug
            ]);

        } catch (\Exception $e) {
            $debug['error'] = $e->getMessage();
            Log::error('Notification error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'debug' => $debug,
            ], 500);
        }
    }

    private function sendFCMNotification(array $data, array &$debug)
    {
        try {
            if (!$this->messaging) {
                throw new \Exception('Firebase messaging not initialized');
            }

            $debug['steps'][] = 'Messaging is initialized';

            $title = $data['chat_type'] === 'direct' 
                ? "New message from {$data['sender_name']}" 
                : "Party chat from {$data['sender_name']}";

            $debug['steps'][] = 'Building notification: ' . $title;

            $message = CloudMessage::withTarget('token', $data['fcm_token'])
                ->withNotification(Notification::create($title, $data['message']))
                ->withData([
                    'sender_id' => (string) $data['sender_id'],
                    'sender_name' => $data['sender_name'],
                    'message_id' => (string) $data['message_id'],
                    'type' => $data['type'],
                    'chat_type' => $data['chat_type'],
                ]);

            $debug['steps'][] = 'Sending FCM message...';

            $result = $this->messaging->send($message);

            $debug['steps'][] = 'FCM sent successfully';
            $debug['result'] = $result;

            return ['success' => true, 'result' => $result];

        } catch (\Exception $e) {
            $debug['steps'][] = 'FCM error: ' . $e->getMessage();
            
            if (str_contains($e->getMessage(), 'registration token') ||
                str_contains($e->getMessage(), 'Invalid token') ||
                str_contains($e->getMessage(), 'NotRegistered')) {
                DeviceToken::where('fcm_token', $data['fcm_token'])
                    ->update(['is_active' => false]);
                $debug['steps'][] = 'Token deactivated';
            }
            
            throw $e;
        }
    }
}