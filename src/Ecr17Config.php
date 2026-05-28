<?php

declare(strict_types=1);

namespace Padosoft\Ecr17;

use Padosoft\Ecr17\Protocol\LrcMode;
use Padosoft\Ecr17\Session\SessionConfig;

/** Immutable client configuration, typically built from config/ecr17.php. */
final class Ecr17Config
{
    public function __construct(
        public readonly string $host = '',
        public readonly int $port = 1024,
        public readonly string $terminalId = '',
        public readonly string $cashRegisterId = '',
        public readonly LrcMode $lrcMode = LrcMode::Std,
        public readonly bool $keepAlive = true,
        public readonly bool $autoReconnect = true,
        public readonly int $connectionTimeoutMs = 5000,
        public readonly int $responseTimeoutMs = 60000,
        public readonly int $ackTimeoutMs = 2000,
        public readonly int $retryCount = 3,
        public readonly int $retryDelayMs = 200,
        public readonly int $receiptDrainMs = 0,
        public readonly bool $debug = false,
    ) {}

    /** @param array<string,mixed> $c */
    public static function fromArray(array $c): self
    {
        return new self(
            host: (string) ($c['host'] ?? ''),
            port: (int) ($c['port'] ?? 1024),
            terminalId: (string) ($c['terminal_id'] ?? ''),
            cashRegisterId: (string) ($c['cash_register_id'] ?? ''),
            lrcMode: LrcMode::fromConfig((string) ($c['lrc_mode'] ?? 'std')),
            keepAlive: (bool) ($c['keep_alive'] ?? true),
            autoReconnect: (bool) ($c['auto_reconnect'] ?? true),
            connectionTimeoutMs: (int) ($c['connection_timeout_ms'] ?? 5000),
            responseTimeoutMs: (int) ($c['response_timeout_ms'] ?? 60000),
            ackTimeoutMs: (int) ($c['ack_timeout_ms'] ?? 2000),
            retryCount: (int) ($c['retry_count'] ?? 3),
            retryDelayMs: (int) ($c['retry_delay_ms'] ?? 200),
            receiptDrainMs: (int) ($c['receipt_drain_ms'] ?? 0),
            debug: (bool) ($c['debug'] ?? false),
        );
    }

    public function toSessionConfig(): SessionConfig
    {
        return new SessionConfig(
            lrcMode: $this->lrcMode,
            ackTimeoutMs: $this->ackTimeoutMs,
            responseTimeoutMs: $this->responseTimeoutMs,
            retryCount: $this->retryCount,
            retryDelayMs: $this->retryDelayMs,
            receiptDrainMs: $this->receiptDrainMs,
        );
    }
}
