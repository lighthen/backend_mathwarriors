<?php
class FcmHelper {
    private static $serviceAccount = null;
    private static $accessToken = null;
    private static $tokenExpiry = 0;

    private static function loadServiceAccount() {
        if (self::$serviceAccount !== null) return;

        $path = __DIR__ . '/../config/firebase-key.json';
        if (!file_exists($path)) {
            throw new Exception('Firebase key file not found at: ' . $path);
        }

        $json = file_get_contents($path);
        self::$serviceAccount = json_decode($json, true);
        if (!self::$serviceAccount) {
            throw new Exception('Invalid Firebase key JSON');
        }
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function getAccessToken() {
        if (self::$accessToken !== null && time() < self::$tokenExpiry) {
            return self::$accessToken;
        }

        self::loadServiceAccount();

        $now = time();
        $header = self::base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $payload = self::base64UrlEncode(json_encode([
            'iss' => self::$serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signature = '';
        $privateKey = self::$serviceAccount['private_key'];
        openssl_sign("$header.$payload", $signature, $privateKey, 'sha256WithRSAEncryption');
        $signature = self::base64UrlEncode($signature);

        $jwt = "$header.$payload.$signature";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to get OAuth token: ' . $response);
        }

        $data = json_decode($response, true);
        self::$accessToken = $data['access_token'];
        self::$tokenExpiry = $now + ($data['expires_in'] ?? 3600) - 60;

        return self::$accessToken;
    }

    public static function sendNotification($fcmToken, $title, $body, $data = []) {
        if (empty($fcmToken)) return;

        $accessToken = self::getAccessToken();
        self::loadServiceAccount();

        $projectId = self::$serviceAccount['project_id'];

        $message = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_merge($data, ['type' => $data['type'] ?? 'info']),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'math_warriors_channel',
                        'priority' => 'high',
                        'default_sound' => true,
                        'default_vibrate_timings' => true,
                    ],
                ],
            ],
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[FCM] Failed to send: $httpCode - $response");
        }

        return $httpCode === 200;
    }
}
