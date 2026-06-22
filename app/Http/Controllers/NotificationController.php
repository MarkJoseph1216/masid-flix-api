<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use GuzzleHttp\Client;

class NotificationController extends Controller
{
    private $accessToken = null;
    private $tokenExpiry = 0;

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
                    'android' => ['priority' => 'high'],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ];

            $debug['steps'][] = 'Sending FCM via V1 API...';

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

            return ['success' => true, 'result' => $result];

        } catch (\Exception $e) {
            $debug['steps'][] = 'FCM error: ' . $e->getMessage();
            
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
            if ($this->accessToken && time() < $this->tokenExpiry - 300) {
                $debug['steps'][] = 'Using cached access token';
                return $this->accessToken;
            }

            $debug['steps'][] = 'Fetching new access token...';

            $client = new GoogleClient();
            $client->setAuthConfig(storage_path('app/firebase/firebase-credentials.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

            $token = $client->fetchAccessTokenWithAssertion();

            if (!isset($token['access_token'])) {
                $debug['steps'][] = 'ERROR: ' . json_encode($token);
                return null;
            }

            $this->accessToken = $token['access_token'];
            $this->tokenExpiry = time() + ($token['expires_in'] ?? 3600);

            $debug['steps'][] = 'Access token obtained';
            return $this->accessToken;

        } catch (\Exception $e) {
            $debug['steps'][] = 'ERROR: ' . $e->getMessage();
            $debug['steps'][] = 'Trace: ' . $e->getTraceAsString();
            return null;
        }
    }
}