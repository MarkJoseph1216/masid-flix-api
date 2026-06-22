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
        $debug = [
            'steps' => [],
            'error' => null,
            'success' => false,
        ];

        try {
            $debug['steps'][] = 'Notification webhook received';
            $debug['steps'][] = 'Request data: ' . json_encode($request->all());

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

            $debug['steps'][] = 'Request validated successfully';

            $result = $this->sendFCMNotification($validated, $debug);

            $debug['steps'][] = 'FCM sent successfully';
            $debug['success'] = true;

            return response()->json([
                'status' => 'success',
                'result' => $result,
                'debug' => $debug,
            ]);

        } catch (\Exception $e) {
            $debug['error'] = $e->getMessage();
            $debug['steps'][] = 'Error: ' . $e->getMessage();
            
            Log::error('Notification error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
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
            $debug['steps'][] = 'Getting Firebase access token with Google Client...';
            
            $accessToken = $this->getAccessToken($debug);
            
            if (!$accessToken) {
                $debug['steps'][] = 'Failed to get Firebase access token';
                throw new \Exception('Could not get Firebase access token');
            }
            
            $debug['steps'][] = 'Access token obtained successfully';
            
            $client = new Client();
            
            $title = $data['chat_type'] === 'direct' 
                ? "New message from {$data['sender_name']}" 
                : "Party chat from {$data['sender_name']}";
            
            $payload = [
                'message' => [
                    'token' => $data['fcm_token'],
                    'notification' => [
                        'title' => $title,
                        'body' => $data['message'],
                    ],
                    'data' => [
                        'sender_id' => (string) $data['sender_id'],
                        'sender_name' => $data['sender_name'],
                        'message_id' => (string) $data['message_id'],
                        'type' => $data['type'],
                        'chat_type' => $data['chat_type'],
                    ],
                    'android' => [
                        'priority' => 'high',
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ];
            
            $debug['steps'][] = 'Sending FCM via HTTP...';
            
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
            $debug['steps'][] = 'FCM response received';
            $debug['fcm_response'] = $result;
            
            return $result;
            
        } catch (\Exception $e) {
            $debug['steps'][] = 'FCM send error: ' . $e->getMessage();
            
            if (str_contains($e->getMessage(), 'registration token') ||
                str_contains($e->getMessage(), 'Invalid token') ||
                str_contains($e->getMessage(), 'NotRegistered')) {
                
                DeviceToken::where('fcm_token', $data['fcm_token'])
                    ->update(['is_active' => false]);
            }
            throw $e;
        }
    }

    private function getAccessToken(array &$debug)
    {
        try {
            $debug['steps'][] = 'Using Google Client library...';
            
            $filePath = storage_path('app/firebase/firebase-credentials.json');
            
            if (!file_exists($filePath)) {
                $debug['steps'][] = 'ERROR: Credentials file not found: ' . $filePath;
                return null;
            }
            
            $debug['steps'][] = 'Credentials file found';
            
            $client = new \Google\Client();
            
            $client->setAuthConfig($filePath);
            
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            
            $debug['steps'][] = 'Google Client configured with credentials';
            
            $accessToken = $client->fetchAccessTokenWithAssertion();
            
            if (!isset($accessToken['access_token'])) {
                $debug['steps'][] = 'ERROR: No access token returned';
                $debug['steps'][] = 'Response: ' . json_encode($accessToken);
                return null;
            }
            
            $debug['steps'][] = 'Access token obtained successfully with Google Client';
            return $accessToken['access_token'];
            
        } catch (\Exception $e) {
            $debug['steps'][] = 'ERROR: ' . $e->getMessage();
            $debug['steps'][] = 'Trace: ' . $e->getTraceAsString();
            return null;
        }
    }
}