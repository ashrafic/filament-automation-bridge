<?php

return [

    'navigation' => [
        'group' => 'Automation Bridge',
        'triggers' => 'Triggers',
        'deliveries' => 'Delivery Logs',
        'templates' => 'Templates',
        'health' => 'Automation Health',
    ],

    'labels' => [
        'trigger' => 'Trigger',
        'triggers' => 'Triggers',
        'delivery' => 'Delivery',
        'deliveries' => 'Delivery Logs',
        'template' => 'Template',
        'templates' => 'Templates',
    ],

    'form' => [
        'sections' => [
            'trigger' => 'When this happens...',
            'trigger_description' => 'Choose the model and event that triggers this automation',
            'conditions' => 'Only if these conditions match',
            'conditions_description' => 'Add rules to filter when this automation should fire (skip to always run)',
            'destination' => 'Then send data to...',
            'destination_description' => 'Choose your automation platform and configure the payload',
            'settings' => 'Settings',
            'settings_description' => 'Name, security, and behavior for this automation',
        ],

        'model' => 'Model',
        'model_placeholder' => 'Choose a model...',
        'model_helper' => 'The Eloquent model to watch for events',

        'trigger_type' => 'Trigger Type',
        'trigger_type_helper' => 'How should this automation fire?',

        'event' => 'On Event',
        'event_helper' => 'Which model event triggers this?',

        'name' => 'Name',
        'name_helper' => 'A descriptive name to identify this automation',

        'description' => 'Description',
        'description_helper' => 'Optional notes about what this automation does',

        'add_condition' => 'Add Condition',
        'condition_field' => 'Field',
        'conditions' => 'Conditions',
        'condition_field_placeholder' => 'Field',
        'condition_operator' => 'Operator',
        'condition_operator_placeholder' => 'Operator',
        'condition_value' => 'Value',
        'condition_value_placeholder' => 'Value',
        'condition_logic' => 'Logic',

        'destination_type' => 'Destination',
        'destination_type_helper' => 'Zapier, Make, n8n, or any custom webhook endpoint',

        'destination_url' => 'Webhook URL',
        'destination_url_placeholder' => 'https://hooks.zapier.com/...',
        'destination_url_helper' => 'Paste the webhook URL from your automation platform',

        'payload_mode' => 'Payload Mode',

        'field_mapping' => 'Include Fields (for Summary mode)',

        'custom_payload_template' => 'Custom Payload Template (for Custom mode)',
        'custom_payload_template_placeholder' => '{"event": "{{ event }}", "data": {{ payload | json }}}',
        'custom_payload_template_helper' => 'Use {{ field }} for model attributes. Example: {"event": "{{ event }}", "name": "{{ name }}"}',

        'active' => 'Active',
        'active_helper' => 'Enable or disable this automation',

        'http_method' => 'HTTP Method',
        'http_method_helper' => 'The HTTP method used to send data to the webhook',

        'secret' => 'Secret',
        'secret_placeholder' => 'Auto-generated if left blank',
        'secret_placeholder_make' => 'sk-...',
        'secret_placeholder_n8n_basic' => 'username:password',
        'secret_placeholder_n8n_bearer' => 'eyJhbGci...',
        'secret_placeholder_n8n_header' => 'x-api-key-abc123',
        'secret_helper_make' => 'Your Make.com API key — sent as x-make-apikey header',
        'secret_helper_n8n_basic' => 'Format: username:password — sent as Basic auth',
        'secret_helper_n8n_bearer' => 'Your JWT or Bearer token — sent as Authorization: Bearer',
        'secret_helper_n8n_header' => 'Your API key — sent as X-Api-Key header',
        'secret_helper_default' => 'HMAC secret for payload signing (auto-generated if empty)',

        'n8n_auth_mode' => 'n8n Auth Mode',
        'n8n_auth_mode_header' => 'API Key (Header Auth)',
        'n8n_auth_mode_basic' => 'Basic Auth (username:password)',
        'n8n_auth_mode_bearer' => 'Bearer Token',
        'n8n_auth_mode_helper' => 'How the secret will be sent. Set to None by leaving secret blank.',

        'n8n_header_name' => 'Header Name',
        'n8n_header_name_helper' => 'Custom header name for Header Auth (e.g. X-Api-Key, Authorization)',

        'request_timeout' => 'Timeout (seconds)',

        'max_retries' => 'Max Retries',

        'no_model_selected' => 'No Model Selected',
        'select_model_first' => 'Select a model first to configure field mapping.',
        'all_fields' => ' (all fields)',

        'payload_preview_fallback' => '// Select a model to see a payload preview',
        'payload_preview_error' => 'Unable to generate preview: ',
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
        'never' => 'Never',
        'na' => 'N/A',

        'trigger' => 'Trigger',
        'model_id' => 'ID: ',
        'response' => 'Response',
        'status' => 'Status',
        'http_status' => 'HTTP Status',
        'source' => 'Source',
        'retries' => 'Retries',
        'duration' => 'Duration',
        'duration_ms' => ' ms',
        'delivered_at' => 'Delivered At',

        'destination_type' => 'Destination Type',

        'empty_templates_heading' => 'No templates yet',
        'empty_templates_description' => 'Save a trigger configuration as a template from the Edit or View page.',
    ],

    'actions' => [
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'duplicate' => 'Duplicate',
        'retry' => 'Retry',
        'test_connection' => 'Test Connection',
        'view_details' => 'Details',
        'view_logs' => 'Delivery Logs',
        'enable' => 'Enable',
        'disable' => 'Disable',
        'retry_selected' => 'Retry Selected',
        'use_template' => 'Use Template',
        'close' => 'Close',
        'copy' => ' (Copy)',
        'send_automation' => 'Send Automation',
        'select_automation_trigger' => 'Select Automation Trigger',
        'save_as_template' => 'Save as Template',
        'template_name' => 'Template Name',
        'create_from_template' => 'Create from Template',
        'template' => 'Template',

        'test_connection_modal_heading' => 'Test Connection',
        'test_connection_modal_description' => 'This will send a test request to the configured destination URL using sample data. No record will be saved.',

        'retry_delivery_modal_heading' => 'Retry Delivery',
        'retry_delivery_modal_description' => 'This will create a new delivery attempt.',

        'details_modal_heading' => 'Delivery #',
    ],

    'notifications' => [
        'created' => 'Automation trigger created successfully.',
        'updated' => 'Automation trigger updated successfully.',
        'deleted' => 'Automation trigger deleted successfully.',
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
        'automation_sent' => 'Automation sent successfully',
        'automation_queued' => 'Automation queued for delivery',
        'connection_successful_title' => 'Connection successful',
        'connection_failed_title' => 'Connection failed',
        'template_saved' => 'Template saved',
        'template_saved_body' => 'Saved as ":name"',
    ],

    'validation' => [
        'trigger_not_found' => 'Automation trigger not found.',
        'trigger_not_active' => 'Automation trigger is not active.',
        'model_class_not_found' => 'Model class does not exist.',
        'sync_already_in_progress' => 'A historical sync is already in progress for this trigger.',
        'delivery_cannot_retry' => 'Delivery cannot be retried.',
    ],

    'widgets' => [
        'health_title' => 'Automation Health',
        'active_triggers' => 'Active Triggers',
        'deliveries_24h' => 'Deliveries (24h)',
        'success_rate' => 'Success Rate',
        'needs_attention' => 'Needs Attention',
        'recent_failures' => 'Recent Failures',
    ],

    'commands' => [
        'install' => [
            'installing' => 'Installing Filament Automation Bridge...',
            'seeding_templates' => 'Seeding built-in templates...',
            'templates_seeded' => 'Built-in templates seeded successfully.',
            'templates_seed_error' => 'Could not seed templates: :error',
            'installed' => 'Filament Automation Bridge installed successfully!',
            'next_steps' => 'Next steps:',
            'add_plugin' => 'Add the plugin to your PanelProvider:',
            'start_queue' => 'Start a queue worker for automation delivery:',
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
            'check_progress' => 'Use `php artisan automation-bridge:sync-progress :uuid` to check progress.',
            'not_found' => 'Automation trigger with ID :id not found.',
            'not_active' => 'Automation trigger [:name] (ID: :id) is not active.',
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
        'http_methods' => [
            'GET' => 'GET',
            'POST' => 'POST',
            'PUT' => 'PUT',
            'PATCH' => 'PATCH',
            'DELETE' => 'DELETE',
        ],
        'http_status_ranges' => [
            '2xx' => '2xx (Success)',
            '3xx' => '3xx (Redirect)',
            '4xx' => '4xx (Client Error)',
            '5xx' => '5xx (Server Error)',
        ],
    ],

];
