<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\Config;
use RuntimeException;

final class EventSigner
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $secret ??= Config::get('security.event_hmac_key');

        if (!$secret) {
            throw new RuntimeException('Event HMAC key is not configured.');
        }

        $this->secret = $secret;
    }

    public function generateEventId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function sign(array $attributes, string $prevHmac = ''): string
    {
        ksort($attributes);
        $normalized = json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($normalized === false) {
            throw new RuntimeException('Failed to normalize event payload for signing.');
        }

        return hash_hmac('sha256', $normalized . $prevHmac, $this->secret);
    }
}
