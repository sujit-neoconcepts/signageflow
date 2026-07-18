<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    /**
     * Send a push notification using Firebase Cloud Messaging (FCM) v1 HTTP API.
     */
    public static function sendPushNotification(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $credentialsPath = storage_path('app/firebase-service-account.json');

        if (! file_exists($credentialsPath)) {
            Log::warning("Firebase service account credentials file not found at {$credentialsPath}. Logging push notification placeholder.");
            Log::info("FCM Push Notification (Placeholder) to token {$deviceToken}: {$title} - {$body}");

            return false;
        }

        try {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            if (! $credentials || ! isset($credentials['project_id']) || ! isset($credentials['private_key']) || ! isset($credentials['client_email'])) {
                Log::error('Invalid Firebase credentials JSON format.');

                return false;
            }

            $projectId = $credentials['project_id'];
            $accessToken = self::getAccessToken($credentials);

            if (! $accessToken) {
                Log::error('Failed to generate Firebase OAuth2 access token.');

                return false;
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            // Construct payload
            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                ],
            ];

            if (! empty($data)) {
                // FCM v1 requires all values in the 'data' array to be strings
                $stringData = [];
                foreach ($data as $key => $value) {
                    $stringData[(string) $key] = (string) $value;
                }
                $payload['message']['data'] = $stringData;
            }

            $response = Http::withToken($accessToken)
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info("FCM Push Notification sent successfully to token {$deviceToken}");

                return true;
            } else {
                Log::error("Failed to send FCM Push Notification. Status: {$response->status()}, Response: {$response->body()}");

                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error sending FCM Push Notification: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Generate OAuth2 Access Token using pure PHP JWT signature.
     */
    private static function getAccessToken(array $credentials): ?string
    {
        $privateKey = $credentials['private_key'];
        $clientEmail = $credentials['client_email'];

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = '';
        $success = openssl_sign(
            $base64UrlHeader.'.'.$base64UrlPayload,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        if (! $success) {
            return null;
        }

        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $base64UrlHeader.'.'.$base64UrlPayload.'.'.$base64UrlSignature;

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json()['access_token'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('OAuth2 token retrieval exception: '.$e->getMessage());
        }

        return null;
    }
}
