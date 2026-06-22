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
            $debug['steps'][] = 'Getting Firebase access token...';
            
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
            $debug['steps'][] = 'Starting Firebase access token generation...';
            
            if (!extension_loaded('openssl')) {
                $debug['steps'][] = 'ERROR: OpenSSL extension is not loaded';
                return null;
            }
            $debug['steps'][] = 'OpenSSL extension is loaded';
            
            $filePath = storage_path('app/firebase/firebase-credentials.json');
            $debug['steps'][] = 'Looking for credentials at: ' . $filePath;
            
            if (!file_exists($filePath)) {
                $debug['steps'][] = 'ERROR: Credentials file not found';
                return null;
            }
            $debug['steps'][] = 'Credentials file exists';
            
            $content = file_get_contents($filePath);
            if ($content === false) {
                $debug['steps'][] = 'ERROR: Could not read credentials file';
                return null;
            }
            
            $debug['steps'][] = 'File size: ' . strlen($content) . ' bytes';
            
            $credentials = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $debug['steps'][] = 'ERROR: Invalid JSON: ' . json_last_error_msg();
                return null;
            }
            
            $debug['steps'][] = 'Project ID: ' . ($credentials['project_id'] ?? 'missing');
            $debug['steps'][] = 'Client Email: ' . ($credentials['client_email'] ?? 'missing');
            
            if (!isset($credentials['private_key'])) {
                $debug['steps'][] = 'ERROR: No private_key found';
                return null;
            }
            
            $privateKey = $credentials['private_key'];
            $debug['steps'][] = 'Private key raw length: ' . strlen($privateKey);
            $privateKey = str_replace('\n', "\n", $privateKey);
            $privateKey = trim($privateKey);
            
            if (!str_contains($privateKey, '-----BEGIN PRIVATE KEY-----')) {
                $debug['steps'][] = 'ERROR: Invalid private key format';
                $debug['steps'][] = 'First 50 chars: ' . substr($privateKey, 0, 50);
                return null;
            }
            $debug['steps'][] = 'Private key has proper format';
            $debug['steps'][] = 'Creating JWT...';
            $now = time();
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT',
            ];
        
            $payload = [
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ];
            
            $base64Header = $this->base64UrlEncode(json_encode($header));
            $base64Payload = $this->base64UrlEncode(json_encode($payload));
            
            $unsignedJwt = $base64Header . '.' . $base64Payload;
            $debug['steps'][] = 'Unsigned JWT created: ' . substr($unsignedJwt, 0, 50) . '...';
            
            $signature = '';
            if (!openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                $debug['steps'][] = 'ERROR: Failed to sign JWT: ' . openssl_error_string();
                return null;
            }
            
            $debug['steps'][] = 'JWT signed successfully';
            
            $base64Signature = $this->base64UrlEncode($signature);
            $jwt = $unsignedJwt . '.' . $base64Signature;
            
            $debug['steps'][] = 'JWT created: ' . substr($jwt, 0, 50) . '...';
            $debug['steps'][] = 'Exchanging JWT for access token...';
            
            $client = new Client();
            $response = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            $debug['steps'][] = 'Token response received';
            
            if (!isset($data['access_token'])) {
                $debug['steps'][] = 'ERROR: No access token in response';
                $debug['steps'][] = 'Response: ' . json_encode($data);
                return null;
            }
            
            $debug['steps'][] = 'Access token obtained successfully';
            return $data['access_token'];
            
        } catch (\Exception $e) {
            $debug['steps'][] = 'ERROR: ' . $e->getMessage();
            $debug['steps'][] = 'Trace: ' . $e->getTraceAsString();
            return null;
        }
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}