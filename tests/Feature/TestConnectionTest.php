<?php

namespace Ashrafic\FilamentWebhookBridge\Tests\Feature;

use Ashrafic\FilamentWebhookBridge\Enums\DeliverySource;
use Ashrafic\FilamentWebhookBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
use Ashrafic\FilamentWebhookBridge\Tests\Fixtures\Models\TestUser;

class TestConnectionTest extends FilamentTestCase
{
    protected $testServerProcess;

    protected $testServerPort;

    protected $testServerTmpFile;

    protected function createTrigger(array $overrides = []): WebhookTrigger
    {
        return WebhookTrigger::create(array_merge([
            'name' => 'Test Connection Trigger',
            'model_class' => TestUser::class,
            'event' => EventEnum::Created,
            'destination_type' => DestinationType::Custom,
            'destination_url' => 'https://example.com/webhook',
            'field_mapping' => ['name', 'email'],
            'payload_mode' => PayloadMode::Summary,
            'active' => true,
            'max_retries' => 0,
        ], $overrides));
    }

    protected function startTestServer(): string
    {
        $script = <<<'PHP'
<?php
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(404);
    echo 'Not found';
}
PHP;

        $this->testServerTmpFile = sys_get_temp_dir().'/webhook_test_'.uniqid().'.php';
        file_put_contents($this->testServerTmpFile, $script);

        $this->testServerProcess = proc_open(
            'php -S 127.0.0.1:0 '.$this->testServerTmpFile,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            null,
            ['suppress_errors' => true]
        );

        usleep(200000);

        $maxRetries = 20;
        $this->testServerPort = null;

        for ($i = 0; $i < $maxRetries; $i++) {
            if (is_resource($this->testServerProcess)) {
                $status = proc_get_status($this->testServerProcess);
                if (! $status['running']) {
                    break;
                }
            }

            $output = '';
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                stream_set_blocking($pipes[2], false);
                $output .= stream_get_contents($pipes[2]);
            }

            if (preg_match('/127\.0\.0\.1:(\d+)/', $output, $matches)) {
                $this->testServerPort = $matches[1];
                break;
            }

            usleep(50000);
        }

        if ($this->testServerPort === null) {
            $this->markTestSkipped('Could not start local test server');
        }

        return 'http://127.0.0.1:'.$this->testServerPort;
    }

    protected function tearDown(): void
    {
        if (is_resource($this->testServerProcess ?? null)) {
            proc_terminate($this->testServerProcess);
            proc_close($this->testServerProcess);
        }

        if ($this->testServerTmpFile && file_exists($this->testServerTmpFile)) {
            @unlink($this->testServerTmpFile);
        }

        parent::tearDown();
    }

    public function test_it_returns_success_for_valid_response(): void
    {
        $baseUrl = $this->startTestServer();
        $trigger = $this->createTrigger([
            'destination_url' => $baseUrl.'/webhook',
        ]);

        $deliveryService = $this->app->make(DeliveryService::class);
        $result = $deliveryService->testConnection($trigger);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['http_status']);
        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertArrayHasKey('response_body', $result);
    }

    public function test_it_returns_details_for_error_response(): void
    {
        $trigger = $this->createTrigger([
            'destination_url' => 'http://127.0.0.1:1/nonexistent',
        ]);

        $deliveryService = $this->app->make(DeliveryService::class);
        $result = $deliveryService->testConnection($trigger);

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    public function test_it_creates_test_delivery_record(): void
    {
        $baseUrl = $this->startTestServer();
        $trigger = $this->createTrigger([
            'destination_url' => $baseUrl.'/webhook',
        ]);

        $deliveryService = $this->app->make(DeliveryService::class);
        $deliveryService->testConnection($trigger);

        $this->assertDatabaseHas('webhook_deliveries', [
            'trigger_id' => $trigger->id,
            'source' => DeliverySource::Test->value,
        ]);

        $delivery = WebhookDelivery::where('trigger_id', $trigger->id)
            ->where('source', DeliverySource::Test->value)
            ->first();

        $this->assertNotNull($delivery);
        $this->assertEquals(DeliverySource::Test, $delivery->source);
    }

    public function test_it_marks_delivery_success_on_200_response(): void
    {
        $baseUrl = $this->startTestServer();
        $trigger = $this->createTrigger([
            'destination_url' => $baseUrl.'/webhook',
        ]);

        $deliveryService = $this->app->make(DeliveryService::class);
        $deliveryService->testConnection($trigger);

        $delivery = WebhookDelivery::where('trigger_id', $trigger->id)
            ->where('source', DeliverySource::Test->value)
            ->first();

        $this->assertNotNull($delivery);
        $this->assertEquals(DeliveryStatus::Success, $delivery->status);
        $this->assertEquals(200, $delivery->http_status);
    }

    public function test_it_marks_delivery_failed_on_connection_error(): void
    {
        $trigger = $this->createTrigger([
            'destination_url' => 'http://127.0.0.1:1/nonexistent',
        ]);

        $deliveryService = $this->app->make(DeliveryService::class);
        $deliveryService->testConnection($trigger);

        $delivery = WebhookDelivery::where('trigger_id', $trigger->id)
            ->where('source', DeliverySource::Test->value)
            ->first();

        $this->assertNotNull($delivery);
        $this->assertEquals(DeliveryStatus::Failed, $delivery->status);
    }
}
