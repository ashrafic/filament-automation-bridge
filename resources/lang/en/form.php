<?php

return [
    'sections.trigger' => 'When this happens...',
    'sections.trigger_description' => 'Choose the model and event that triggers this automation',
    'sections.conditions' => 'Only if these conditions match',
    'sections.conditions_description' => 'Add rules to filter when this automation should fire (skip to always run)',
    'sections.destination' => 'Then send data to...',
    'sections.destination_description' => 'Choose your automation platform and configure the payload',
    'sections.settings' => 'Settings',
    'sections.settings_description' => 'Name, security, and behavior for this automation',

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
];
