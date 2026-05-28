<?php

declare(strict_types=1);

return [
    // Network
    'host' => env('ECR17_HOST', ''),
    'port' => (int) env('ECR17_PORT', 1024),

    // Identity
    'terminal_id' => env('ECR17_TERMINAL_ID', ''),
    'cash_register_id' => env('ECR17_CASH_REGISTER_ID', ''),

    // LRC scope: stx | std | noext | stx_noext
    'lrc_mode' => env('ECR17_LRC_MODE', 'std'),

    // Behaviour
    'keep_alive' => (bool) env('ECR17_KEEP_ALIVE', true),
    'auto_reconnect' => (bool) env('ECR17_AUTO_RECONNECT', true),

    // Timeouts (ms)
    'connection_timeout_ms' => (int) env('ECR17_CONNECTION_TIMEOUT_MS', 5000),
    'response_timeout_ms' => (int) env('ECR17_RESPONSE_TIMEOUT_MS', 60000),
    'ack_timeout_ms' => (int) env('ECR17_ACK_TIMEOUT_MS', 2000),

    // Retransmission
    'retry_count' => (int) env('ECR17_RETRY_COUNT', 3),
    'retry_delay_ms' => (int) env('ECR17_RETRY_DELAY_MS', 200),

    // After a result, keep forwarding 'S' receipt lines until this many ms of
    // silence (0 = off).
    'receipt_drain_ms' => (int) env('ECR17_RECEIPT_DRAIN_MS', 0),

    'debug' => (bool) env('ECR17_DEBUG', false),
];
