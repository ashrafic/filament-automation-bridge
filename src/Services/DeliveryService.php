<?php

namespace Ashrafic\FilamentWebhookBridge\Services;

use Ashrafic\FilamentWebhookBridge\Enums\DeliverySource;
use Ashrafic\FilamentWebhookBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Events\WebhookDispatched;
use Ashrafic\FilamentWebhookBridge\Exceptions\DeliveryFailedException;
use Ashrafic\FilamentWebhookBridge\Jobs\ProcessWebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class DeliveryService
{
    public function __construct(
        protected ConditionEvaluator $conditionEvaluator,
        protected PayloadBuilder $payloadBuilder,
        protected SecurityService $securityService,
        protected RateLimiterService $rateLimiterService,
    ) {}

    public function dispatch(WebhookTrigger $trigger, Model $model, EventEnum $event, array $original = []): ?WebhookDelivery
    {
        try {
            if (! $this->conditionEvaluator->evaluate($model, $trigger->conditions, $original)) {
                return null;
            }
        } catch (\Throwable $e) {
            Log::error('DeliveryService: condition evaluation failed', [
                'trigger_id' => $trigger->id,
                'model' => get_class($model),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        try {
            $payload = $this->payloadBuilder->build($trigger, $model);
        } catch (\Throwable $e) {
            Log::error('DeliveryService: payload build failed', [
                'trigger_id' => $trigger->id,
                'model' => get_class($model),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $headers = $this->securityService->sign($payload, $trigger->secret);

        $delivery = WebhookDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'payload' => $payload,
            'headers' => $headers,
            'status' => DeliveryStatus::Pending,
            'retry_count' => 0,
            'max_retries' => $trigger->max_retries ?? config('filament-webhook-bridge.retry.default_max_attempts', 3),
            'source' => DeliverySource::Realtime,
            'dispatched_at' => now(),
        ]);

        if (config('filament-webhook-bridge.sandbox_mode', false)) {
            $delivery->markSuccess(200, [], 'Sandbox mode - delivery simulated', 0);

            Log::info('DeliveryService: sandbox mode - delivery simulated', [
                'delivery_id' => $delivery->id,
                'trigger_id' => $trigger->id,
            ]);

            return $delivery;
        }

        try {
            $this->rateLimiterService->throttle($trigger->destination_url);
        } catch (DeliveryFailedException $e) {
            $delivery->markFailed(0, null, $e->getMessage(), null);

            Log::warning('DeliveryService: rate limited', [
                'delivery_id' => $delivery->id,
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
            ]);

            return $delivery;
        }

        $queue = config('filament-webhook-bridge.queue.queue_name', 'webhooks');
        $connection = config('filament-webhook-bridge.queue.connection');

        ProcessWebhookDelivery::dispatch(
            $delivery->id,
            $delivery->payload,
            $trigger->destination_url,
            $trigger->secret,
            $delivery->headers ?? [],
            $trigger->webhook_timeout ?? 30,
            $trigger->max_retries ?? config('filament-webhook-bridge.retry.default_max_attempts', 3),
            $delivery->uuid,
        )->onQueue($queue)->onConnection($connection);

        WebhookDispatched::dispatch($delivery);

        return $delivery;
    }

    public function getActiveTriggers(string $modelClass, EventEnum $event): Collection
    {
        return Cache::remember(
            "webhook_bridge.triggers.{$modelClass}.{$event->value}",
            300,
            fn () => WebhookTrigger::active()->forModelEvent($modelClass, $event)->get(),
        );
    }

    public function retry(WebhookDelivery $delivery): WebhookDelivery
    {
        if (! $delivery->canRetry()) {
            throw new \RuntimeException('Delivery cannot be retried.');
        }

        $trigger = $delivery->trigger;

        $newDelivery = WebhookDelivery::create([
            'trigger_id' => $delivery->trigger_id,
            'model_type' => $delivery->model_type,
            'model_id' => $delivery->model_id,
            'payload' => $delivery->payload,
            'headers' => $delivery->headers,
            'status' => DeliveryStatus::Pending,
            'retry_count' => $delivery->retry_count + 1,
            'max_retries' => $delivery->max_retries,
            'source' => DeliverySource::ManualRetry,
            'dispatched_at' => now(),
        ]);

        $queue = config('filament-webhook-bridge.queue.queue_name', 'webhooks');
        $connection = config('filament-webhook-bridge.queue.connection');

        ProcessWebhookDelivery::dispatch(
            $newDelivery->id,
            $newDelivery->payload,
            $trigger->destination_url,
            $trigger->secret,
            $newDelivery->headers ?? [],
            $trigger->webhook_timeout ?? 30,
            $newDelivery->max_retries,
            $newDelivery->uuid,
        )->onQueue($queue)->onConnection($connection);

        return $newDelivery;
    }

    public function bulkRetry(array|Collection $deliveryIds): int
    {
        $ids = $deliveryIds instanceof Collection ? $deliveryIds->toArray() : $deliveryIds;

        $deliveries = WebhookDelivery::whereIn('id', $ids)
            ->whereNot('status', DeliveryStatus::Pending)
            ->whereNot('status', DeliveryStatus::Cancelled)
            ->get();

        $queued = 0;

        foreach ($deliveries as $delivery) {
            if (! $delivery->canRetry()) {
                continue;
            }

            try {
                $this->retry($delivery);
                $queued++;
            } catch (\Throwable $e) {
                Log::warning('DeliveryService: bulk retry failed for delivery', [
                    'delivery_id' => $delivery->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $queued;
    }

    public function testConnection(WebhookTrigger $trigger): array
    {
        $payload = $this->payloadBuilder->buildSample($trigger);

        $headers = $this->securityService->sign($payload, $trigger->secret);

        $allHeaders = array_merge([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Filament-Webhook-Bridge/1.0 (Laravel)',
            'X-Webhook-Trigger-Id' => (string) $trigger->id,
            'X-Webhook-Test' => 'true',
            'Accept' => 'application/json',
        ], $headers);

        $delivery = WebhookDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => $trigger->model_class,
            'model_id' => null,
            'payload' => $payload,
            'headers' => $headers,
            'status' => DeliveryStatus::Pending,
            'retry_count' => 0,
            'max_retries' => 0,
            'source' => DeliverySource::Test,
            'dispatched_at' => now(),
        ]);

        $startTime = microtime(true);
        $durationMs = 0;
        $httpStatus = null;
        $responseBody = null;
        $responseHeaders = [];
        $error = null;

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 30]);

            $response = $client->post($trigger->destination_url, [
                'json' => $payload,
                'headers' => $allHeaders,
            ]);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $httpStatus = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $responseHeaders = $this->parseResponseHeaders($response);

            if ($httpStatus >= 200 && $httpStatus < 300) {
                $delivery->markSuccess($httpStatus, $responseHeaders, $responseBody, $durationMs);

                $success = true;
            } else {
                $delivery->markFailed(0, $httpStatus, "Unexpected status code: {$httpStatus}", $durationMs);

                $success = false;
                $error = "Unexpected status code: {$httpStatus}";
            }
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $error = $e->getMessage();

            $delivery->markFailed(0, null, $error, $durationMs);

            $success = false;
        }

        return [
            'http_status' => $httpStatus,
            'response_body' => $responseBody,
            'response_headers' => $responseHeaders,
            'duration_ms' => $durationMs,
            'success' => $success,
            'error' => $error,
        ];
    }

    public function handleSpatieSuccess(WebhookDelivery $delivery, ResponseInterface $response): void
    {
        $responseBody = $response->getBody()->getContents();
        $responseHeaders = $this->parseResponseHeaders($response);

        $delivery->markSuccess(
            $response->getStatusCode(),
            $responseHeaders,
            $responseBody,
            $delivery->duration_ms ?? 0,
        );
    }

    public function handleSpatieFailure(WebhookDelivery $delivery, \Throwable $exception): void
    {
        $delivery->markFailed(
            $delivery->retry_count + 1,
            null,
            $exception->getMessage(),
            $delivery->duration_ms,
        );
    }

    public function cancelPendingDeliveries(WebhookTrigger $trigger): int
    {
        return WebhookDelivery::where('trigger_id', $trigger->id)
            ->where('status', DeliveryStatus::Pending)
            ->update(['status' => DeliveryStatus::Cancelled->value]);
    }

    protected function parseResponseHeaders(ResponseInterface $response): array
    {
        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }
}