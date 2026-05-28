<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Transport;

/**
 * Low-level byte transport for the ECR17 LAN connection.
 *
 * Unlike the React Native port (which uses a native reader thread + callbacks),
 * the PHP transport is SYNCHRONOUS: the session pumps {@see read()} in its wait
 * loops. Byte buffers are PHP (binary) strings.
 */
interface TransportInterface
{
    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;

    /**
     * Non-destructive liveness probe: returns false if the peer has closed the
     * connection (half-open socket) even when {@see isConnected()} still reports
     * true. Used to reconnect PROACTIVELY before sending, so a financial command
     * never starts on a stale socket (which would surface a false "disconnected"
     * and — correctly — not be retried). Must NOT consume buffered bytes.
     */
    public function isAlive(): bool;

    /** Send raw bytes (a fully framed packet). */
    public function send(string $bytes): void;

    /**
     * Block up to $timeoutMs for incoming bytes and return whatever arrived
     * ('' on timeout / no data). The session accumulates and frames the stream.
     */
    public function read(int $timeoutMs): string;
}
