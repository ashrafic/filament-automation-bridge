<?php

namespace Ashrafic\FilamentWebhookBridge\Services;

use Ashrafic\FilamentWebhookBridge\Exceptions\SecurityException;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SecurityService
{
    public function sign(array $payload, ?string $secret): array
    {
        if ($secret === null) {
            return [];
        }

        $timestamp = time();
        $signature = hash_hmac(
            'sha256',
            $timestamp . '.' . json_encode($payload),
            $secret
        );

        return [
            'X-Webhook-Signature' => 'sha256=' . $signature,
            'X-Webhook-Timestamp' => (string) $timestamp,
        ];
    }

    public function validateUrl(string $url): void
    {
        if (empty($url)) {
            throw new SecurityException('URL cannot be empty.');
        }

        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            throw new SecurityException('Invalid URL format.');
        }

        $scheme = strtolower($parsed['scheme']);
        $allowedSchemes = config('filament-webhook-bridge.security.allowed_schemes', ['https', 'http']);

        if (! in_array($scheme, $allowedSchemes)) {
            throw new SecurityException("URL scheme '{$scheme}' is not allowed.");
        }

        if (config('filament-webhook-bridge.security.require_https_in_production', true)
            && app()->environment('production')
            && $scheme === 'http'
        ) {
            throw new SecurityException('HTTP URLs are not allowed in production.');
        }

        if ($this->isBlockedIp($url)) {
            throw new SecurityException('URL resolves to a blocked IP address.');
        }
    }

    public function isBlockedIp(string $url): bool
    {
        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['host'])) {
            return true;
        }

        $hostname = $parsed['host'];

        $blockedHostnames = ['localhost', '127.0.0.1', '[::1]', '0.0.0.0'];

        if (in_array($hostname, $blockedHostnames, true)) {
            return true;
        }

        $ip = gethostbyname($hostname);

        if ($ip === $hostname) {
            Log::warning('SecurityService: DNS resolution failed for hostname', [
                'hostname' => $hostname,
            ]);

            return true;
        }

        $blockedRanges = config('filament-webhook-bridge.security.blocked_ip_ranges', [
            '127.0.0.0/8',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '::1/128',
            'fc00::/7',
        ]);

        foreach ($blockedRanges as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    public function generateSecret(): string
    {
        return hash('sha256', Str::random(64));
    }

    public function encryptPayloadFields(array $payload, array $fieldsToEncrypt): array
    {
        foreach ($fieldsToEncrypt as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = encrypt($payload[$field]);
            }
        }

        return $payload;
    }

    public function buildHeaders(WebhookTrigger $trigger, WebhookDelivery $delivery): array
    {
        $payload = $delivery->payload ?? [];
        $signHeaders = $this->sign($payload, $trigger->secret);

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Filament-Webhook-Bridge/1.0 (Laravel)',
            'X-Webhook-Trigger-Id' => (string) $trigger->id,
            'X-Webhook-Delivery-Id' => (string) $delivery->id,
            'X-Webhook-Attempt' => (string) ($delivery->retry_count + 1),
            'Accept' => 'application/json',
        ];

        return array_merge($headers, $signHeaders);
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $prefixLength] = explode('/', $cidr, 2);
        $prefixLength = (int) $prefixLength;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $ipLen = strlen($ipBin);
        $subnetLen = strlen($subnetBin);

        if ($ipLen !== $subnetLen) {
            return false;
        }

        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        for ($i = 0; $i < $fullBytes; $i++) {
            if ($ipBin[$i] !== $subnetBin[$i]) {
                return false;
            }
        }

        if ($remainingBits > 0 && $fullBytes < $ipLen) {
            $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($subnetBin[$fullBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }
}