<?php

namespace Ashrafic\FilamentWebhookBridge\Formatters;

use Ashrafic\FilamentWebhookBridge\Contracts\PayloadFormatter;
use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;

class CustomFormatter implements PayloadFormatter
{
    public function destinationType(): DestinationType
    {
        return DestinationType::Custom;
    }

    public function format(array $payload, array $metadata): array
    {
        if (! isset($metadata['custom_template'])) {
            return $payload;
        }

        $template = $metadata['custom_template'];

        return $this->renderTemplate($template, $payload);
    }

    private function renderTemplate(string $template, array $payload): array
    {
        $result = [];

        preg_match_all('/(\w+)\s*=\s*(.+?)(?=\s+\w+\s*=|$)/s', $template, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            $rendered = $this->replacePlaceholders($template, $payload);

            return ['output' => $rendered];
        }

        foreach ($matches as $match) {
            $key = trim($match[1]);
            $value = $this->replacePlaceholders(trim($match[2]), $payload);
            $result[$key] = $value;
        }

        return $result;
    }

    private function replacePlaceholders(string $template, array $payload): string
    {
        return preg_replace_callback('/\{\{\s*(\w+(?:\.\w+)*)\s*\}\}/', function ($match) use ($payload) {
            $keys = explode('.', $match[1]);
            $value = $payload;

            foreach ($keys as $key) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } else {
                    return $match[0];
                }
            }

            return is_string($value) || is_numeric($value) ? (string) $value : $match[0];
        }, $template);
    }
}
