<?php

namespace Ashrafic\FilamentAutomationBridge\Services;

use Ashrafic\FilamentAutomationBridge\Enums\DeliverySource;
use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Events\AutomationDispatched;
use Ashrafic\FilamentAutomationBridge\Exceptions\DeliveryFailedException;
use Ashrafic\FilamentAutomationBridge\Jobs\ProcessAutomationDelivery;
use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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

    public function dispatch(AutomationTrigger $trigger, Model $model, EventEnum $event, array $original = [], array $context = []): ?AutomationDelivery
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

        $payload['context'] = array_merge($payload['context'] ?? [], $context);

        $headers = $this->securityService->sign($payload, $trigger->secret);

        $delivery = AutomationDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'payload' => $payload,
            'headers' => $headers,
            'status' => DeliveryStatus::Pending,
            'retry_count' => 0,
            'max_retries' => $trigger->max_retries ?? config('filament-automation-bridge.retry.default_max_attempts', 3),
            'source' => DeliverySource::Realtime,
            'dispatched_at' => now(),
        ]);

        if (config('filament-automation-bridge.sandbox_mode', false)) {
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

        $queue = config('filament-automation-bridge.queue.queue_name', 'webhooks');
        $connection = config('filament-automation-bridge.queue.connection');

        ProcessAutomationDelivery::dispatch(
            $delivery->id,
            $delivery->payload,
            $trigger->destination_url,
            $trigger->secret,
            $delivery->headers ?? [],
            $trigger->request_timeout ?? 30,
            $trigger->max_retries ?? config('filament-automation-bridge.retry.default_max_attempts', 3),
            $delivery->uuid,
        )->onQueue($queue)->onConnection($connection);

        AutomationDispatched::dispatch($delivery);

        return $delivery;
    }

    public function dispatchForSchedule(AutomationTrigger $trigger, Model $model): ?AutomationDelivery
    {
        return $this->dispatchGeneric($trigger, $model, DeliverySource::Realtime, [
            'schedule_type' => $trigger->trigger_config['schedule_type'] ?? 'daily',
            'triggered_at' => now()->toIso8601String(),
        ]);
    }

    public function dispatchForDateCondition(AutomationTrigger $trigger, Model $model, array $contextData = []): ?AutomationDelivery
    {
        return $this->dispatchGeneric($trigger, $model, DeliverySource::Realtime, array_merge([
            'triggered_at' => now()->toIso8601String(),
        ], $contextData));
    }

    public function dispatchForManualTrigger(AutomationTrigger $trigger, Model $model, ?int $userId = null): ?AutomationDelivery
    {
        return $this->dispatchGeneric($trigger, $model, DeliverySource::ManualRetry, [
            'user_id' => $userId ?? auth()->id(),
            'trigger_source' => 'manual',
            'triggered_at' => now()->toIso8601String(),
        ]);
    }

    public function dispatchForEventTrigger(AutomationTrigger $trigger, Model $model, array $eventProperties = []): ?AutomationDelivery
    {
        return $this->dispatchGeneric($trigger, $model, DeliverySource::Realtime, [
            'event_class' => $trigger->trigger_config['event_class'] ?? '',
            'event_properties' => $eventProperties,
            'triggered_at' => now()->toIso8601String(),
        ]);
    }

    protected function dispatchGeneric(AutomationTrigger $trigger, Model $model, DeliverySource $source, array $triggerContext = []): ?AutomationDelivery
    {
        try {
            if (! $this->conditionEvaluator->evaluate($model, $trigger->conditions)) {
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
            $payload = $this->buildGenericPayload($trigger, $model, $triggerContext);
        } catch (\Throwable $e) {
            Log::error('DeliveryService: payload build failed', [
                'trigger_id' => $trigger->id,
                'model' => get_class($model),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $headers = $this->securityService->sign($payload, $trigger->secret);

        $delivery = AutomationDelivery::create([
            'trigger_id' => $trigger->id,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'payload' => $payload,
            'headers' => $headers,
            'status' => DeliveryStatus::Pending,
            'retry_count' => 0,
            'max_retries' => $trigger->max_retries ?? config('filament-automation-bridge.retry.default_max_attempts', 3),
            'source' => $source,
            'dispatched_at' => now(),
        ]);

        if (config('filament-automation-bridge.sandbox_mode', false)) {
            $delivery->markSuccess(200, [], 'Sandbox mode - delivery simulated', 0);

            Log::info('DeliveryService: sandbox mode - delivery simulated (generic)', [
                'delivery_id' => $delivery->id,
                'trigger_id' => $trigger->id,
            ]);

            return $delivery;
        }

        try {
            $this->rateLimiterService->throttle($trigger->destination_url);
        } catch (DeliveryFailedException $e) {
            $delivery->markFailed(0, null, $e->getMessage(), null);

            Log::warning('DeliveryService: rate limited (generic)', [
                'delivery_id' => $delivery->id,
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
            ]);

            return $delivery;
        }

        $queue = config('filament-automation-bridge.queue.queue_name', 'webhooks');
        $connection = config('filament-automation-bridge.queue.connection');

        ProcessAutomationDelivery::dispatch(
            $delivery->id,
            $delivery->payload,
            $trigger->destination_url,
            $trigger->secret,
            $delivery->headers ?? [],
            $trigger->request_timeout ?? 30,
            $trigger->max_retries ?? config('filament-automation-bridge.retry.default_max_attempts', 3),
            $delivery->uuid,
        )->onQueue($queue)->onConnection($connection);

        AutomationDispatched::dispatch($delivery);

        return $delivery;
    }

    protected function buildGenericPayload(AutomationTrigger $trigger, Model $model, array $triggerContext = []): array
    {
        $payloadMode = $trigger->payload_mode;

        $data = match ($payloadMode) {
            PayloadMode::Summary => $this->payloadBuilder->extractFields($model, $trigger->field_mapping ?? []),
            PayloadMode::All => $this->payloadBuilder->extractAllAttributesProxy($model),
            PayloadMode::Custom => $this->renderGenericTemplate($trigger->custom_payload_template ?? '', $model, $trigger->trigger_type),
        };

        $eventValue = $trigger->trigger_type ?? 'generic';

        $envelope = [
            'event' => $eventValue,
            'model' => get_class($model),
            'triggered_at' => now()->toIso8601String(),
            'automation_id' => $trigger->id,
            'trigger_context' => $triggerContext,
            'data' => $data,
        ];

        return $envelope;
    }

    protected function renderGenericTemplate(string $template, Model $model, string $triggerType): array
    {
        if (empty(trim($template))) {
            return $model->toArray();
        }

        $replacements = [
            'event' => $triggerType,
            'model' => get_class($model),
        ];

        $modelAttributes = $model->toArray();
        $allValues = array_merge($replacements, $modelAttributes);

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)(?:\s*\|\s*json)?\s*\}\}/',
            function ($match) use ($allValues) {
                $key = $match[1];
                $value = data_get($allValues, $key, $match[0]);

                if (is_array($value) || is_object($value)) {
                    return json_encode($value) ?: $match[0];
                }

                return (string) $value;
            },
            $template,
        );

        $decoded = json_decode($rendered, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return ['raw' => $rendered];
        }

        return $decoded;
    }

    protected function extractAllAttributesProxy(Model $model): array
    {
        $hidden = $model->getHidden();
        $excluded = config('filament-automation-bridge.field_schema.excluded_attributes', [
            'password',
            'remember_token',
            'api_token',
        ]);

        $attributes = $model->getAttributes();
        $result = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $hidden)) {
                continue;
            }

            if (in_array($key, $excluded)) {
                continue;
            }

            if ($this->isBinaryColumn($key, $model)) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    protected function isBinaryColumn(string $field, Model $model): bool
    {
        return false;
    }

    public function getActiveTriggers(string $modelClass, EventEnum $event): Collection
    {
        return Cache::remember(
            "automation_bridge.triggers.{$modelClass}.{$event->value}",
            300,
            fn () => AutomationTrigger::active()
                ->forModelEvent($modelClass, $event)
                ->get(),
        );
    }

    public function retry(AutomationDelivery $delivery): AutomationDelivery
    {
        if (! $delivery->canRetry()) {
            throw new \RuntimeException('Delivery cannot be retried.');
        }

        $trigger = $delivery->trigger;

        $newDelivery = AutomationDelivery::create([
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

        $queue = config('filament-automation-bridge.queue.queue_name', 'webhooks');
        $connection = config('filament-automation-bridge.queue.connection');

        ProcessAutomationDelivery::dispatch(
            $newDelivery->id,
            $newDelivery->payload,
            $trigger->destination_url,
            $trigger->secret,
            $newDelivery->headers ?? [],
            $trigger->request_timeout ?? 30,
            $newDelivery->max_retries,
            $newDelivery->uuid,
        )->onQueue($queue)->onConnection($connection);

        return $newDelivery;
    }

    public function bulkRetry(array|Collection $deliveryIds): int
    {
        $ids = $deliveryIds instanceof Collection ? $deliveryIds->toArray() : $deliveryIds;

        $deliveries = AutomationDelivery::whereIn('id', $ids)
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

    public function testConnection(AutomationTrigger $trigger): array
    {
        $payload = $this->payloadBuilder->buildSample($trigger);

        $headers = $this->securityService->sign($payload, $trigger->secret);

        $allHeaders = array_merge([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Filament-Automation-Bridge/1.0 (Laravel)',
            'X-Automation-Trigger-Id' => (string) $trigger->id,
            'X-Automation-Test' => 'true',
            'Accept' => 'application/json',
        ], $headers);

        $delivery = AutomationDelivery::create([
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
            $client = new Client(['timeout' => 30]);

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

    public function handleSpatieSuccess(AutomationDelivery $delivery, ResponseInterface $response): void
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

    public function handleSpatieFailure(AutomationDelivery $delivery, \Throwable $exception): void
    {
        $delivery->markFailed(
            $delivery->retry_count + 1,
            null,
            $exception->getMessage(),
            $delivery->duration_ms,
        );
    }

    public function cancelPendingDeliveries(AutomationTrigger $trigger): int
    {
        return AutomationDelivery::where('trigger_id', $trigger->id)
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
