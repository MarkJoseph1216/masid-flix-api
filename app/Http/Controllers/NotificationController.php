<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class NotificationController extends Controller
{
    private $messaging;

    public function __construct()
    {
        try {
            $filePath = storage_path('app/firebase/firebase-credentials.json');
            
            if (!file_exists($filePath)) {
                Log::error('Credentials file not found at: ' . $filePath);
                if (!is_dir(dirname($filePath))) {
                    mkdir(dirname($filePath), 0755, true);
                }
                $credentials = [
                    'type' => 'service_account',
                    'project_id' => 'ichatyou',
                    'private_key_id' => '34205e0424080293bc30f9d938e0827b19843d76',
                    'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCxfkcwn1YPXU6M\n683FPiGFxbrEadAml4CnLCj3rgUSIpmimLANLQ2hR1nW1HbRhetNzuhiG8kWK5l8\nFfuPlcIeVwKcBotOb1C4MySF1uaWZxRWOGGVYFffHny6TAnSnZnebaxu+FaxX+d4\nWNyLgHdxCGiizhkviig4OZYbjAlGqB8n0T9OLqeL54LhlBbu/3Cd0MvYHxDUrXHi\n9udjdBTrcGoP+j2eZZ6F9lvDwsnA3l+FWgRxMgOG0JE8j7qJhQrVruz+JpOcg8D1\n1uMjLZKPsefHBaEo1rPiRoATlxdEVcJNxfhSYqgxk9e0L79vXhZ2xx8fa8z+VC3D\ncJ9YEGHFAgMBAAECggEAFWTmuNYj3fM9wh+0LFe6W7EDO6STeeteDwh0IbKgmth/\n00j7Q4NQNsXubsYqUkQFolnTyeuWd+0mcX4G1f5TqSuMvXjOdtRVEvbbKqTGI4/m\nNCRUotg7j0HR//SlZHUptFVc6P1XGcc5E9kGMGx6OS4typ30DDZndat/S++7uH/c\nvdEY2ahm3bXgaUcJ2vxQvemBwKrfCUdaEUvDDm6edvhVVaDPCPhpDiYlxqH+Q8DP\nId144KTGcRPvK5S4NJzyEQbfL0iABpCfA1FHnoR9m3ExhKHFLXS8fVaJijPYP36I\nIT7xG/3tJAtCnNf3nuhiC+XACMrGqMcWYcBacVJz4QKBgQDnaU+ey5plBf11agKx\n/Wo70475VyuoHnleZ9WpyeJA5PpjxJtxoCAm7onLxHu9aGk7k7MUL1GzR2+rfA3e\n9mizQeKpL1idf+F9ceQCnYU+5STTmPwtT4HaASkgVy7XjjnlucKQaE1Rafs6qpAN\npi4u+hCng64A8Acurcp2ZB3ZtQKBgQDEWlMyyZt8YeLKGpfz02wnvSPwBVy945O2\n579w1EkgTwbweC9IYQBbr+KG3ZC+zbRByQbCK7BIihjdV4MD3LPOoDIUx7OORwBa\n8qAxPRQHH28OD4AFJ7X6GxcXSDca5u/yKyQ9MBC7nYjwg9QU7F1r6OTw9Jd2B4kv\n/fv3vK4x0QKBgDZDs7AA/ouCBBVsboVeb3LoATbnAg3CV6OTpb7S4INnLnAGwoy6\nh8+ZUCbARGP9/+9Ai1XIYtgvgDguNvJ5xcODR6t0tsr4GeBYvKcAWSaOhTw5O6lE\nY6bDbuluiEVzzI/aJ43FZ5wXxhnTtUP+HAZYDV+6uSrvHkAL8NYiU/2hAoGAOctE\nVdyVkYTWVhqBw1jlqsS3QTyy0Ymcvudzp+g0Jfhc2Ibnk+xJSLN6f6vToPW3Ku3a\nuWhWmONc8jmB7K8Xlaf9VbR6G1S2vA5SLGwH6xjLfV3+loXbwGQc5dNxtM9orOUZ\n45C0PCTgW7rRv97amJqSWIIF2s3ZCXE+quq0cyECgYBinoj5AoXdZgzpNoR4Kz6Z\ngDuqxaVqZxFbZ5WkEEbCefOyV/R2sYtbFWKz+5v9rz3RXpbYuHJAO82Lxar0b657\nT64ZL4YcIuffxQCvJ+OAIeb5s+nVvlhG4xnJ3G9Qx9YwDpg7PUVsk/yHOI2CnWAT\n/TRy5KS7fUO7myjIBglDMw==\n-----END PRIVATE KEY-----\n",
                    'client_email' => 'firebase-adminsdk-fbsvc@ichatyou.iam.gserviceaccount.com',
                    'client_id' => '110210875032238205549',
                    'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
                    'token_uri' => 'https://oauth2.googleapis.com/token',
                    'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
                    'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-fbsvc%40ichatyou.iam.gserviceaccount.com',
                    'universe_domain' => 'googleapis.com'
                ];
                file_put_contents($filePath, json_encode($credentials, JSON_PRETTY_PRINT));
                Log::info('Credentials file created at: ' . $filePath);
            }
            
    
            $factory = (new Factory)->withServiceAccount($filePath);
            $this->messaging = $factory->createMessaging();
            Log::info('Firebase initialized successfully using file path');
            
        } catch (\Exception $e) {
            Log::error('Firebase init failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }

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
            if (!$this->messaging) {
                throw new \Exception('Firebase messaging not initialized');
            }

            $debug['steps'][] = 'Messaging is initialized';

            $title = $data['chat_type'] === 'direct' 
                ? "New message from {$data['sender_name']}" 
                : "Party chat from {$data['sender_name']}";

            $debug['steps'][] = 'Building notification: ' . $title;

            $message = CloudMessage::fromArray([
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