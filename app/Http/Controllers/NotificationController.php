<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class NotificationController extends Controller
{
    public function handle(Request $request)
    {
        $debug = ['steps' => [], 'error' => null, 'success' => false];

        try {
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

            $result = $this->sendFCMNotification($validated, $debug);
            $debug['success'] = true;

            return response()->json(['status' => 'success', 'result' => $result, 'debug' => $debug]);

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
        $accessToken = $this->getAccessToken($debug);
        if (!$accessToken) {
            throw new \Exception('Could not get Firebase access token');
        }

        $client = new Client();
        $title = $data['chat_type'] === 'direct' 
            ? "New message from {$data['sender_name']}" 
            : "Party chat from {$data['sender_name']}";

        $payload = [
            'message' => [
                'token' => $data['fcm_token'],
                'notification' => ['title' => $title, 'body' => $data['message']],
                'data' => [
                    'sender_id' => (string) $data['sender_id'],
                    'sender_name' => $data['sender_name'],
                    'message_id' => (string) $data['message_id'],
                    'type' => $data['type'],
                    'chat_type' => $data['chat_type'],
                ],
                'android' => ['priority' => 'high'],
                'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
            ],
        ];

        $response = $client->post(
            'https://fcm.googleapis.com/v1/projects/ichatyou/messages:send',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]
        );

        $result = json_decode($response->getBody(), true);
        return $result;
    }

    private function getAccessToken(array &$debug)
    {
        try {
            $filePath = storage_path('app/firebase/firebase-credentials.json');
            
            if (!file_exists($filePath)) {
                $debug['steps'][] = 'ERROR: Credentials file not found';
                return null;
            }

            $client = new \Google\Client();
            $client->setAuthConfig($filePath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            
            $accessToken = $client->fetchAccessTokenWithAssertion();
            
            if (!isset($accessToken['access_token'])) {
                $debug['steps'][] = 'ERROR: No access token returned';
                return null;
            }

            return $accessToken['access_token'];

        } catch (\Exception $e) {
            $debug['steps'][] = 'ERROR: ' . $e->getMessage();
            return null;
        }
    }
}