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

            $result = $this->sendFCMNotification($validated);

            Log::info('FCM sent successfully');
            return response()->json(['status' => 'success', 'result' => $result]);

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
            $accessToken = $this->getAccessToken();
            
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
            
            Log::info('Sending FCM via HTTP...');
            
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
            Log::info('FCM sent successfully: ' . json_encode($result));
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('FCM send error: ' . $e->getMessage());
            
            if (str_contains($e->getMessage(), 'registration token') ||
                str_contains($e->getMessage(), 'Invalid token') ||
                str_contains($e->getMessage(), 'NotRegistered')) {
                
                Log::warning('Token deactivated: ' . substr($data['fcm_token'], 0, 20) . '...');
                DeviceToken::where('fcm_token', $data['fcm_token'])
                    ->update(['is_active' => false]);
            }
            throw $e;
        }
    }

    private function getAccessToken()
    {
        try {
            $credentialsJson = env('FIREBASE_SERVICE_ACCOUNT');
            
            if (!$credentialsJson || $credentialsJson === '') {
                Log::error('FIREBASE_SERVICE_ACCOUNT env variable not set');
                return null;
            }
            
            $credentials = json_decode($credentialsJson, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON in FIREBASE_SERVICE_ACCOUNT: ' . json_last_error_msg());
                return null;
            }
            
            if (isset($credentials['private_key'])) {
                $credentials['private_key'] = str_replace('\n', "\n", $credentials['private_key']);
                if (!str_contains($credentials['private_key'], '-----BEGIN PRIVATE KEY-----')) {
                    Log::error('Invalid private key format');
                    return null;
                }
            } else {
                Log::error('No private_key found in credentials');
                return null;
            }
            
            Log::info('Credentials loaded for: ' . ($credentials['client_email'] ?? 'unknown'));
            
            $client = new Client();
            
            $header = json_encode([
                'typ' => 'JWT',
                'alg' => 'RS256',
            ]);
            
            $now = time();
            $payload = json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ]);
            
            $base64Header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
            $base64Payload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
            $unsignedJwt = $base64Header . '.' . $base64Payload;
            
            $privateKey = $credentials['private_key'];
            $signature = '';
            
            if (!openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                Log::error('Failed to sign JWT');
                return null;
            }
            
            $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            $jwt = $unsignedJwt . '.' . $base64Signature;
            
            Log::info('JWT created, exchanging for access token...');
            
            $response = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['access_token'])) {
                Log::error('No access token in response: ' . json_encode($data));
                return null;
            }
            
            Log::info('Access token obtained successfully');
            return $data['access_token'];
            
        } catch (\Exception $e) {
            Log::error('Failed to get access token: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return null;
        }
    }
}