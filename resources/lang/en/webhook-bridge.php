<?php

return [

    'navigation' => [
        'group' => 'Integrations',
        'triggers' => 'Webhook Triggers',
        'deliveries' => 'Delivery Logs',
        'health' => 'Webhook Health',
    ],

    'labels' => [
        'trigger' => 'Webhook Trigger',
        'triggers' => 'Webhook Triggers',
        'delivery' => 'Delivery',
        'deliveries' => 'Delivery Logs',
        'template' => 'Webhook Template',
        'templates' => 'Webhook Templates',
    ],

    'form' => [
        'trigger_configuration' => 'Trigger Configuration',
        'payload_configuration' => 'Payload Configuration',
        'conditions' => 'Conditions (Optional)',
        'security_settings' => 'Security & Settings',

        'name' => 'Name',
        'description' => 'Description',
        'model' => 'Model',
        'event' => 'Event',
        'destination_type' => 'Destination Type',
        'destination_url' => 'Destination URL',
        'payload_mode' => 'Payload Mode',
        'field_mapping' => 'Fields',
        'custom_payload_template' => 'Custom Payload Template',
        'conditions_field' => 'Conditions',
        'active' => 'Active',
        'secret' => 'Secret',
        'webhook_timeout' => 'Timeout (seconds)',
        'max_retries' => 'Max Retries',
        'ip_whitelist' => 'IP Whitelist',
        'encrypt_payload' => 'Encrypt Payload',

        'condition_field' => 'Field',
        'condition_operator' => 'Operator',
        'condition_value' => 'Value',
        'condition_logic' => 'Logic',

        'select_model' => 'Select a model',
        'leave_blank_auto_generate' => 'Leave blank to auto-generate',
    ],

    'table' => [
        'name' => 'Name',
        'model' => 'Model',
        'event' => 'Event',
        'destination' => 'Destination',
        'active' => 'Active',
        'last_delivered' => 'Last Delivered',
        'success_rate' => 'Success Rate',
        'created_at' => 'Created At',

        'trigger' => 'Trigger',
        'model_id' => 'Model ID',
        'status' => 'Status',
        'http_status' => 'HTTP Status',
        'source' => 'Source',
        'retries' => 'Retries',
        'duration' => 'Duration',
        'delivered_at' => 'Delivered At',

        'never' => 'Never',
    ],

    'actions' => [
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save',
        'toggle_active' => 'Toggle Active',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'duplicate' => 'Duplicate',
        'retry' => 'Retry',
        'test_connection' => 'Test Connection',
        'sync' => 'Sync Historical',
        'view_details' => 'Details',
        'view_logs' => 'Delivery Logs',
        'bulk_enable' => 'Enable',
        'bulk_disable' => 'Disable',
        'bulk_retry' => 'Retry Selected',
    ],

    'notifications' => [
        'created' => 'Webhook trigger created successfully.',
        'updated' => 'Webhook trigger updated successfully.',
        'deleted' => 'Webhook trigger deleted successfully.',
        'activated' => 'Trigger activated',
        'deactivated' => 'Trigger deactivated',
        'duplicated' => 'Trigger duplicated',
        'enabled' => 'Selected triggers enabled',
        'disabled' => 'Selected triggers disabled',

        'connection_successful' => 'Connection Successful',
        'connection_failed' => 'Connection Failed',
        'test_failed' => 'Test Failed',

        'retry_queued' => 'Delivery retry queued',
        'retry_failed' => 'Retry failed',
        'cannot_retry' => 'Cannot retry this delivery',

        'bulk_retry_queued' => ':count delivery(s) retry queued',

        'validation_error' => 'Validation Error',
        'fill_model_and_url' => 'Please fill in the Model and Destination URL before testing.',
    ],

    'validation' => [
        'trigger_not_found' => 'Webhook trigger not found.',
        'trigger_not_active' => 'Webhook trigger is not active.',
        'model_class_not_found' => 'Model class does not exist.',
        'sync_already_in_progress' => 'A historical sync is already in progress for this trigger.',
        'delivery_cannot_retry' => 'Delivery cannot be retried.',
    ],

    'widgets' => [
        'health_title' => 'Webhook Health',
        'active_triggers' => 'Active Triggers',
        'deliveries_24h' => 'Deliveries (24h)',
        'success_rate' => 'Success Rate',
        'needs_attention' => 'Needs Attention',
        'recent_failures' => 'Recent Failures',
    ],

    'commands' => [
        'install' => [
            'installing' => 'Installing Filament Webhook Bridge...',
            'seeding_templates' => 'Seeding built-in templates...',
            'templates_seeded' => 'Built-in templates seeded successfully.',
            'templates_seed_error' => 'Could not seed templates: :error',
            'installed' => 'Filament Webhook Bridge installed successfully!',
            'next_steps' => 'Next steps:',
            'add_plugin' => 'Add the plugin to your PanelProvider:',
            'start_queue' => 'Start a queue worker for webhook delivery:',
        ],
        'prune' => [
            'no_logs' => 'No delivery logs older than :days days found.',
            'dry_run' => '[DRY RUN] :count delivery log(s) would be deleted (older than :days days).',
            'pruned' => ':count delivery log(s) pruned (older than :days days).',
        ],
        'model_cache' => [
            'clearing' => 'Clearing model discovery cache...',
            'rebuilt' => 'Model discovery cache rebuilt. :count model(s) discovered.',
        ],
        'sync' => [
            'starting' => 'Starting historical sync for trigger [:name] (ID: :id)...',
            'model' => 'Model: :model',
            'event' => 'Event: :event',
            'batch_size' => 'Batch size: :size',
            'apply_conditions' => 'Apply conditions: :apply',
            'started' => 'Historical sync started. Batch UUID: :uuid',
            'check_progress' => 'Use `php artisan webhook-bridge:sync-progress :uuid` to check progress.',
            'not_found' => 'Webhook trigger with ID :id not found.',
            'not_active' => 'Webhook trigger [:name] (ID: :id) is not active.',
            'failed' => 'Failed to start sync: :error',
        ],
        'test' => [
            'testing' => 'Testing connection for trigger [:name] (ID: :id)...',
            'destination' => 'Destination: :url',
            'connection_successful' => 'Connection successful!',
            'connection_failed' => 'Connection failed!',
            'http_status' => 'HTTP Status: :status',
            'response_time' => 'Response Time: :time ms',
            'error' => 'Error: :error',
            'response_body' => 'Response Body:',
            'test_failed' => 'Test failed: :error',
        ],
    ],

    'enums' => [
        'event' => [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'restored' => 'Restored',
            'force_deleted' => 'Force Deleted',
        ],
        'destination_type' => [
            'zapier' => 'Zapier',
            'make' => 'Make',
            'n8n' => 'n8n',
            'custom' => 'Custom',
        ],
        'payload_mode' => [
            'summary' => 'Summary (Selected Fields)',
            'all' => 'All Fields',
            'custom' => 'Custom Template',
        ],
        'delivery_status' => [
            'pending' => 'Pending',
            'success' => 'Success',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
        ],
        'delivery_source' => [
            'realtime' => 'Realtime',
            'historical_sync' => 'Historical Sync',
            'test' => 'Test',
            'manual_retry' => 'Manual Retry',
        ],
        'condition_operators' => [
            'equals' => 'Equals',
            'not_equals' => 'Not Equals',
            'contains' => 'Contains',
            'greater_than' => 'Greater Than',
            'less_than' => 'Less Than',
            'is_empty' => 'Is Empty',
            'is_not_empty' => 'Is Not Empty',
            'changed' => 'Changed',
            'changed_to' => 'Changed To',
        ],
        'condition_logic' => [
            'and' => 'AND',
            'or' => 'OR',
        ],
        'http_status_ranges' => [
            '2xx' => '2xx (Success)',
            '3xx' => '3xx (Redirect)',
            '4xx' => '4xx (Client Error)',
            '5xx' => '5xx (Server Error)',
        ],
    ],

];