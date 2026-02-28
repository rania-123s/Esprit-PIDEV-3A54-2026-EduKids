<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ChatWebSocketTokenService
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret
    ) {
    }

    public function createToken(int $userId, int $ttlSeconds = 3600): string
    {
        $payload = [
            'uid' => $userId,
            'exp' => time() + max(60, $ttlSeconds),
        ];

        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->secret, true));

        return $encodedPayload . '.' . $signature;
    }

    public function resolveUserId(string $token): ?int
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;
        if ($encodedPayload === '' || $encodedSignature === '') {
            return null;
        }

        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->secret, true));
        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return null;
        }

        $decodedPayload = $this->base64UrlDecode($encodedPayload);
        if ($decodedPayload === null) {
            return null;
        }

        $payload = json_decode($decodedPayload, true);
        if (!is_array($payload)) {
            return null;
        }

        $exp = (int) ($payload['exp'] ?? 0);
        $uid = (int) ($payload['uid'] ?? 0);
        if ($exp < time() || $uid <= 0) {
            return null;
        }

        return $uid;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        return $decoded === false ? null : $decoded;
    }
}

