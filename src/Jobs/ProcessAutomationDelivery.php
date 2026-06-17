<?php

namespace Ashrafic\FilamentAutomationBridge\Jobs;

use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentAutomationBridge\Events\AutomationDeliveryCompleted;
use Ashrafic\FilamentAutomationBridge\Events\AutomationDeliveryFailed;
use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAutomationDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct(
        public int $deliveryId,
        public array $payload,
        public string $destinationUrl,
        public ?string $secret,
        public array $headers,
        public int $requestTimeout,
        public int $maxRetries,
        public string $deliveryUuid,
        public bool $checkActive = true,
    ) {
        $this->tries = $this->maxRetries + 1;
        $this->queue = config('filament-automation-bridge.queue.queue_name', 'webhooks');
    }

    public function handle(): void
    {
        $delivery = AutomationDelivery::find($this->deliveryId);

        if ($delivery === null) {
            Log::warning('ProcessAutomationDelivery: delivery not found', [
                'delivery_id' => $this->deliveryId,
            ]);

            return;
        }

        if ($delivery->status === DeliveryStatus::Cancelled) {
            return;
        }

        if ($this->checkActive) {
            $trigger = $delivery->trigger;

            if ($trigger === null || ! $trigger->active) {
                $delivery->update(['status' => DeliveryStatus::Cancelled->value]);

                return;
            }
        }

        if (config('filament-automation-bridge.sandbox_mode', false)) {
            $delivery->markSuccess(200, [], 'Sandbox mode - delivery simulated', 0);

            event(new AutomationDeliveryCompleted($delivery));

            return;
        }

        $allHeaders = array_merge([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Filament-Automation-Bridge/1.0 (Laravel)',
            'X-Automation-Delivery-Id' => $this->deliveryUuid,
            'Accept' => 'application/json',
        ], $this->headers);

        $startTime = microtime(true);

        try {
            $client = new Client([
                'timeout' => $this->webhookTimeout,
                'http_errors' => false,
            ]);

            $response = $client->post($this->destinationUrl, [
                'json' => $this->payload,
                'headers' => $allHeaders,
            ]);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $httpStatus = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            $responseHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $responseHeaders[$name] = implode(', ', $values);
            }

            if ($httpStatus >= 200 && $httpStatus < 300) {
                $delivery->markSuccess($httpStatus, $responseHeaders, $responseBody, $durationMs);

                event(new AutomationDeliveryCompleted($delivery));
            } else {
                $retryableStatusCodes = config('filament-automation-bridge.retry.retryable_status_codes', [408, 429, 500, 502, 503, 504]);

                if (in_array($httpStatus, $retryableStatusCodes)) {
                    Log::warning('ProcessAutomationDelivery: retryable HTTP error', [
                        'delivery_id' => $this->deliveryId,
                        'http_status' => $httpStatus,
                        'attempt' => $this->attempts(),
                    ]);

                    throw new \RuntimeException("Retryable HTTP error: {$httpStatus}");
                }

                $delivery->markFailed(
                    $this->attempts(),
                    $httpStatus,
                    "HTTP error: {$httpStatus}",
                    $durationMs,
                );

                event(new AutomationDeliveryFailed($delivery, "HTTP error: {$httpStatus}"));
            }
        } catch (ConnectException $e) {
            Log::warning('ProcessAutomationDelivery: connection error', [
                'delivery_id' => $this->deliveryId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (GuzzleException $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $delivery->markFailed(
                $this->attempts(),
                null,
                $e->getMessage(),
                $durationMs,
            );

            event(new AutomationDeliveryFailed($delivery, $e->getMessage()));
        }
    }

    public function failed(\Throwable $exception): void
    {
        $delivery = AutomationDelivery::find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $delivery->markFailed(
            $this->maxRetries,
            null,
            $exception->getMessage(),
            null,
        );

        event(new AutomationDeliveryFailed($delivery, $exception->getMessage()));
    }

    public function backoff(): array
    {
        $base = config('filament-automation-bridge.retry.backoff_base', 10);

        return array_map(
            fn (int $i) => $base * (int) pow(10, $i),
            range(0, $this->maxRetries - 1),
        );
    }

    public function tags(): array
    {
        return ['automation-bridge', 'delivery:'.$this->deliveryId];
    }
}
