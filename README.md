<div class="filament-hidden">

# Filament Automation Bridge

---

</div>

> **Turn any Eloquent model event into an automation trigger for Zapier, Make, or n8n — without writing code.**

Define triggers from your Filament admin panel and instantly connect to Zapier, Make, n8n, or any webhook endpoint. Six trigger types, nine condition operators, native auth per platform, and full delivery monitoring — all without touching a line of integration code.

---

<div class="filament-hidden">

[![Packagist Version](https://img.shields.io/packagist/v/ashrafic/filament-automation-bridge?style=flat-square&color=blue&logo=packagist)](https://packagist.org/packages/ashrafic/filament-automation-bridge)
[![Docs](https://img.shields.io/badge/docs-filament--automation--bridge-blue?style=flat-square&logo=readthedocs)](https://docs.ashraficlabs.com/filament-automation-bridge)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-777bb4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Filament](https://img.shields.io/badge/filament-%5E4.0%20%7C%7C%20%5E5.0-fbbf24?style=flat-square&logo=laravel)](https://filamentphp.com)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square&logo=open-source-initiative)](LICENSE.md)

---

</div>

## Installation

```bash
composer require ashrafic/filament-automation-bridge
php artisan automation-bridge:install
php artisan migrate
```

Add the plugin to your PanelProvider:

```php
use Ashrafic\FilamentAutomationBridge\FilamentAutomationBridgePlugin;

$panel->plugin(FilamentAutomationBridgePlugin::make());
```

Start a queue worker for delivery processing:

```bash
php artisan queue:work
```

Done. An **Automation Bridge** navigation group appears in your panel.

---

## How It Works

```
Model Event → Condition Evaluation → Payload Builder → HMAC Sign → Queue → Webhook POST → Delivery Log
```

1. **A trigger fires** — model event, status change, schedule, date condition, manual action, or Laravel event
2. **Conditions are evaluated** — 9 operators with AND/OR logic; `changed` / `changed_to` operators for update-aware filtering
3. **A payload is built** — hand-picked fields, all attributes, or a custom JSON template
4. **The payload is signed** — platform-native auth: HMAC, API key, Basic Auth, or Bearer token
5. **The delivery is queued** — every call is logged with full request/response details, retried with exponential backoff

---

## Features

Adds an **Automation Bridge** navigation group to your panel with Triggers, Templates, and Delivery Log.

| Feature | Details |
|---|---|
| **6 Trigger Types** | Model events, status changes, schedules, date conditions, manual actions, and any Laravel event class |
| **Visual Condition Builder** | 9 operators (equals, contains, greater/less than, changed, changed_to) with AND/OR logic |
| **4 Destination Types** | Zapier, Make, n8n, and Custom webhook — each with native payload formatting |
| **Smart Payload Builder** | Summary (selected fields), All (every attribute), or Custom JSON templates with `{{ field }}` placeholders |
| **Platform-Native Auth** | Zapier (URL secrecy), Make (x-make-apikey), n8n (API Key / Basic Auth / Bearer Token), HMAC signing |
| **Configurable HTTP Method** | GET, POST, PUT, PATCH, or DELETE per trigger |
| **Delivery Monitoring** | Full log with status, HTTP code, response body, duration; success-rate tracking per trigger |
| **Automatic Retries** | Exponential backoff with configurable retryable HTTP status codes; bulk retry from the UI |
| **Historical Sync** | Backfill existing records into any trigger via the queue with progress tracking and cancellation |
| **Health Dashboard Widget** | Active triggers, 24h deliveries, success rate (color-coded), recent failures with one-click retry |
| **Templates** | Save any trigger configuration and apply it to new models — standardize payloads across your team |
| **Model Discovery** | Auto-scans `app/Models/` for Eloquent models; configurable paths and exclusions |
| **Field Schema Analysis** | Introspects model attributes and relations (up to 3 levels deep); powers the field picker and payload preview |
| **SSRF Prevention** | Blocks webhook calls to localhost and private IP ranges |
| **Sandbox Mode** | Capture and log deliveries without sending externally — safe development and testing |
| **Rate Limiting** | Per-destination-hostname throttling via Laravel's RateLimiter |

---

## Full Guides

Full documentation at **[docs.ashraficlabs.com/filament-automation-bridge](https://docs.ashraficlabs.com/filament-automation-bridge)**

| | |
|---|---|
| [Getting Started](https://docs.ashraficlabs.com/filament-automation-bridge/getting-started) | Full workflow: create triggers, test, monitor deliveries |
| [Installation](https://docs.ashraficlabs.com/filament-automation-bridge/installation) | Requirements, composer, panel registration, quick start |
| [Configuration](https://docs.ashraficlabs.com/filament-automation-bridge/configuration) | Full config reference — every option |
| [Authorization](https://docs.ashraficlabs.com/filament-automation-bridge/authorization) | 6 gates for fine-grained access control |
| [Features Overview](https://docs.ashraficlabs.com/filament-automation-bridge/features) | Architecture and capability map |
| [Trigger Types](https://docs.ashraficlabs.com/filament-automation-bridge/features/trigger-types) | All 6 trigger types with config and use cases |
| [Conditions](https://docs.ashraficlabs.com/filament-automation-bridge/features/conditions) | 9 operators, AND/OR logic, changed tracking |
| [Destinations](https://docs.ashraficlabs.com/filament-automation-bridge/features/destinations) | Zapier, Make, n8n, Custom — auth, formatting, HTTP method |
| [Payloads](https://docs.ashraficlabs.com/filament-automation-bridge/features/payloads) | Summary, All, Custom modes; field mapping; schema analysis |
| [Templates](https://docs.ashraficlabs.com/filament-automation-bridge/features/templates) | Save, reuse, and manage trigger configurations |
| [Delivery Monitoring](https://docs.ashraficlabs.com/filament-automation-bridge/features/delivery-monitoring) | Delivery log, retries, health widget, retention |
| [Historical Sync](https://docs.ashraficlabs.com/filament-automation-bridge/features/historical-sync) | Batch backfill, progress tracking, cancellation |
| [Model Discovery](https://docs.ashraficlabs.com/filament-automation-bridge/features/model-discovery) | Auto-scan, cache, HasAutomationTriggers trait |
| [Events](https://docs.ashraficlabs.com/filament-automation-bridge/reference/events) | 8 dispatchable events for hooks and integrations |
| [Exceptions](https://docs.ashraficlabs.com/filament-automation-bridge/reference/exceptions) | 7 exception classes with factory methods |
| [Commands](https://docs.ashraficlabs.com/filament-automation-bridge/reference/commands) | 5 artisan commands reference |

---

## Screenshots

| Triggers Dashboard | Create Trigger |
|---|---|
| [![Triggers Dashboard](https://docs.ashraficlabs.com/filament-automation-bridge/assets/screenshots/triggers_list.png)](https://docs.ashraficlabs.com/filament-automation-bridge/getting-started) | [![Create Trigger](https://docs.ashraficlabs.com/filament-automation-bridge/assets/screenshots/trigger_create_top.png)](https://docs.ashraficlabs.com/filament-automation-bridge/getting-started) |

| Destination & Payload | Delivery Log |
|---|---|
| [![Destination & Payload](https://docs.ashraficlabs.com/filament-automation-bridge/assets/screenshots/trigger_create_bottom.png)](https://docs.ashraficlabs.com/filament-automation-bridge/getting-started) | [![Delivery Log](https://docs.ashraficlabs.com/filament-automation-bridge/assets/screenshots/delivery_log_list.png)](https://docs.ashraficlabs.com/filament-automation-bridge/features/delivery-monitoring) |

| Templates | Delivery Details |
|---|---|
| [![Templates](https://docs.ashraficlabs.com/filament-automation-bridge/assets/screenshots/templates_list.png)](https://docs.ashraficlabs.com/filament-automation-bridge/features/templates) | [![Delivery Details](https://docs.ashraficlabs.com/filament-automation-bridge/assets/screenshots/delivery_log_details.png)](https://docs.ashraficlabs.com/filament-automation-bridge/features/delivery-monitoring) |

---

## Requirements

- PHP 8.2+
- Laravel 11+
- Filament v4.x / v5.x

---

## Testing

```bash
composer test
```

---

## License

MIT. See [LICENSE.md](LICENSE.md).

---

<br>
<br>

<p align="center">
  <a href="https://ashraficlabs.com">
    <img src="https://ashraficlabs.com/brand/ashrafic-labs-logo-horizontal-primary.svg" alt="Ashrafic Labs" width="200" />
  </a>
</p>

<p align="center">
  <em>Built with precision for professionals.</em><br>
  <a href="mailto:packages@ashraficlabs.com">packages@ashraficlabs.com</a>
</p>
