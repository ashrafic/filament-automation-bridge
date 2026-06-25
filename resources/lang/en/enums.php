<?php

return [
    'event.created' => 'Created',
    'event.updated' => 'Updated',
    'event.deleted' => 'Deleted',
    'event.restored' => 'Restored',
    'event.force_deleted' => 'Force Deleted',

    'destination_type.zapier' => 'Zapier',
    'destination_type.make' => 'Make',
    'destination_type.n8n' => 'n8n',
    'destination_type.custom' => 'Custom',

    'payload_mode.summary' => 'Summary (Selected Fields)',
    'payload_mode.all' => 'All Fields',
    'payload_mode.custom' => 'Custom Template',

    'delivery_status.pending' => 'Pending',
    'delivery_status.success' => 'Success',
    'delivery_status.failed' => 'Failed',
    'delivery_status.cancelled' => 'Cancelled',

    'delivery_source.realtime' => 'Realtime',
    'delivery_source.historical_sync' => 'Historical Sync',
    'delivery_source.test' => 'Test',
    'delivery_source.manual_retry' => 'Manual Retry',

    'condition_operators.equals' => 'Equals',
    'condition_operators.not_equals' => 'Not Equals',
    'condition_operators.contains' => 'Contains',
    'condition_operators.greater_than' => 'Greater Than',
    'condition_operators.less_than' => 'Less Than',
    'condition_operators.is_empty' => 'Is Empty',
    'condition_operators.is_not_empty' => 'Is Not Empty',
    'condition_operators.changed' => 'Changed',
    'condition_operators.changed_to' => 'Changed To',

    'condition_logic.and' => 'AND',
    'condition_logic.or' => 'OR',

    'http_methods.GET' => 'GET',
    'http_methods.POST' => 'POST',
    'http_methods.PUT' => 'PUT',
    'http_methods.PATCH' => 'PATCH',
    'http_methods.DELETE' => 'DELETE',

    'http_status_ranges.2xx' => '2xx (Success)',
    'http_status_ranges.3xx' => '3xx (Redirect)',
    'http_status_ranges.4xx' => '4xx (Client Error)',
    'http_status_ranges.5xx' => '5xx (Server Error)',
];
