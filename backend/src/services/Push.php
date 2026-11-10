<?php
declare(strict_types=1);

/**
 * Push notifications through Firebase Cloud Messaging (HTTP v1).
 *
 * Setup: Firebase console → Project settings → Service accounts → "Generate new private key".
 * Upload that JSON file as storage/firebase-service-account.json (storage/ is not web accessible),
 * or point config 'fcm.service_account_file' somewhere else.
 *
 * Messages carry a `notification` block (so Android shows them, and the app shows them while open)
 * plus `data` in the format expo-notifications expects, so a tap opens the right screen.
 */
final class Push
{
    private static ?array $account = null;

    public static function accountFile(): string
    {
        return (string)config('fcm.service_account_file', STORAGE_PATH . '/firebase-service-account.json');
    }

    public static function account(): ?array
    {
        if (self::$account !== null) return self::$account ?: null;
        $file = self::accountFile();
        $json = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        $ok = is_array($json) && !empty($json['project_id']) && !empty($json['private_key']) && !empty($json['client_email']);
        self::$account = $ok ? $json : [];
        return $ok ? $json : null;
    }

    public static function configured(): bool
    {
        return self::account() !== null;
    }

    /** OAuth access token for FCM, cached until shortly before it expires. */
    private static function accessToken(): ?string
    {
        $acc = self::account();
        if (!$acc) return null;
        $cache = STORAGE_PATH . '/cache/fcm_token.json';
        if (is_file($cache)) {
            $c = json_decode((string)file_get_contents($cache), true);
            if (($c['exp'] ?? 0) > time() + 120 && ($c['sub'] ?? '') === $acc['client_email']) return $c['token'];
        }
        $now = time();
        $head = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = b64url(json_encode([
            'iss' => $acc['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
        ]));
        if (!openssl_sign("$head.$claims", $sig, $acc['private_key'], OPENSSL_ALGO_SHA256)) {
            log_line('push', 'Could not sign Google token — check the service account private key');
            return null;
        }
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "$head.$claims." . b64url($sig),
            ]),
        ]);
        $res = json_decode((string)curl_exec($ch), true);
        if (empty($res['access_token'])) {
            log_line('push', 'Google token error: ' . json_encode($res));
            return null;
        }
        @mkdir(dirname($cache), 0775, true);
        file_put_contents($cache, json_encode(['token' => $res['access_token'], 'exp' => $now + (int)($res['expires_in'] ?? 3600), 'sub' => $acc['client_email']]));
        return $res['access_token'];
    }

    /**
     * Send one notification to many device tokens in parallel.
     * Tokens Firebase reports as dead are deleted. Returns [sent, failed].
     *
     * @param string[] $tokens
     * @param array<string,scalar> $data  e.g. ['screen' => 'special_task', 'id' => 3]
     */
    public static function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (!$tokens || !self::configured()) return [0, 0];
        $access = self::accessToken();
        if (!$access) return [0, count($tokens)];

        $url = 'https://fcm.googleapis.com/v1/projects/' . self::account()['project_id'] . '/messages:send';
        $payloadData = [
            'title' => $title,
            'message' => $body,
            'channelId' => 'default',
            'body' => json_encode(array_map('strval', $data)), // becomes notification.request.content.data in the app
        ];
        $sent = 0;
        $failed = 0;
        $dead = [];

        foreach (array_chunk($tokens, 25) as $chunk) {
            $mh = curl_multi_init();
            $handles = [];
            foreach ($chunk as $tok) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access, 'Content-Type: application/json'],
                    // `notification` makes it show while the app is open too (expo-notifications drops data-only
                    // messages in the foreground); `data` carries the screen to open when it's tapped.
                    CURLOPT_POSTFIELDS => json_encode(['message' => [
                        'token' => $tok,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => $payloadData,
                        'android' => [
                            'priority' => 'high',
                            'notification' => ['channel_id' => 'default', 'sound' => 'default', 'default_vibrate_timings' => true],
                        ],
                    ]]),
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$tok] = $ch;
            }
            do {
                $status = curl_multi_exec($mh, $running);
                if ($running) curl_multi_select($mh, 1.0);
            } while ($running && $status === CURLM_OK);

            foreach ($handles as $tok => $ch) {
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($code === 200) {
                    $sent++;
                } else {
                    $failed++;
                    $err = (string)curl_multi_getcontent($ch);
                    if ($code === 404 || str_contains($err, 'UNREGISTERED') || (str_contains($err, 'INVALID_ARGUMENT') && str_contains($err, 'registration token'))) {
                        $dead[] = $tok;
                    } elseif ($failed <= 3) {
                        log_line('push', "FCM $code: " . substr($err, 0, 300));
                    }
                }
                curl_multi_remove_handle($mh, $ch);
            }
            curl_multi_close($mh);
        }
        if ($dead) {
            q('DELETE FROM push_tokens WHERE token IN (' . implode(',', array_fill(0, count($dead), '?')) . ')', $dead);
        }
        return [$sent, $failed];
    }

    public static function toUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = array_column(rows('SELECT token FROM push_tokens WHERE user_id = ?', [$userId]), 'token');
        if ($tokens) self::send($tokens, $title, $body, $data);
    }
}

/*
 * Pushes for single users are queued during the request and sent after the response has gone out,
 * so a slow Firebase call never delays the app or holds a database transaction open.
 * Pushes queued inside a transaction that rolls back are dropped (see tx()).
 */
function queue_push(int $userId, string $title, string $body, array $data = []): void
{
    static $registered = false;
    $GLOBALS['push_queue'][] = [$userId, $title, $body, $data];
    if (!$registered) {
        $registered = true;
        register_shutdown_function(function () {
            $queue = $GLOBALS['push_queue'] ?? [];
            if (!$queue || !Push::configured()) return;
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            foreach ($queue as [$uid, $t, $b, $d]) {
                try { Push::toUser($uid, $t, $b, $d); } catch (Throwable $e) { log_line('push', $e->getMessage()); }
            }
        });
    }
}
