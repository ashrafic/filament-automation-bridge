<?php

return [
    'install.installing' => 'Installing Filament Automation Bridge...',
    'install.seeding_templates' => 'Seeding built-in templates...',
    'install.templates_seeded' => 'Built-in templates seeded successfully.',
    'install.templates_seed_error' => 'Could not seed templates: :error',
    'install.installed' => 'Filament Automation Bridge installed successfully!',
    'install.next_steps' => 'Next steps:',
    'install.add_plugin' => 'Add the plugin to your PanelProvider:',
    'install.start_queue' => 'Start a queue worker for automation delivery:',

    'prune.no_logs' => 'No delivery logs older than :days days found.',
    'prune.dry_run' => '[DRY RUN] :count delivery log(s) would be deleted (older than :days days).',
    'prune.pruned' => ':count delivery log(s) pruned (older than :days days).',

    'model_cache.clearing' => 'Clearing model discovery cache...',
    'model_cache.rebuilt' => 'Model discovery cache rebuilt. :count model(s) discovered.',

    'sync.starting' => 'Starting historical sync for trigger [:name] (ID: :id)...',
    'sync.model' => 'Model: :model',
    'sync.event' => 'Event: :event',
    'sync.batch_size' => 'Batch size: :size',
    'sync.apply_conditions' => 'Apply conditions: :apply',
    'sync.started' => 'Historical sync started. Batch UUID: :uuid',
    'sync.check_progress' => 'Use `php artisan automation-bridge:sync-progress :uuid` to check progress.',
    'sync.not_found' => 'Automation trigger with ID :id not found.',
    'sync.not_active' => 'Automation trigger [:name] (ID: :id) is not active.',
    'sync.failed' => 'Failed to start sync: :error',

    'test.testing' => 'Testing connection for trigger [:name] (ID: :id)...',
    'test.destination' => 'Destination: :url',
    'test.connection_successful' => 'Connection successful!',
    'test.connection_failed' => 'Connection failed!',
    'test.http_status' => 'HTTP Status: :status',
    'test.response_time' => 'Response Time: :time ms',
    'test.error' => 'Error: :error',
    'test.response_body' => 'Response Body:',
    'test.test_failed' => 'Test failed: :error',
];
