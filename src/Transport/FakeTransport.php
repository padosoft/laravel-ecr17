<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Transport;

use Padosoft\Ecr17\Protocol\PacketCodec;

/**
 * In-memory transport for unit tests. Deterministic and synchronous, mirroring
 * the RN FakeTransport: every time the session sends an APPLICATION request (a
 * frame starting with STX) the next queued response is made available to read().
 * Control sends (ACK/NAK) are recorded but never trigger a reply.
 */
final class FakeTransport implements TransportInterface
{
    private bool $connected = false;

    /** @var list<string> scripted replies, delivered one per application send */
    private array $responses = [];

    private string $pending = ''; // bytes ready for the next read()

    /** @var list<string> every frame sent */
    private array $sent = [];

    private bool $disconnectOnRequest = false;

    private bool $dropped = false;

    public function connect(): void
    {
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function send(string $bytes): void
    {
        $this->sent[] = $bytes;
        $isApplicationRequest = $bytes !== '' && ord($bytes[0]) === PacketCodec::STX;

        if ($isApplicationRequest && $this->disconnectOnRequest) {
            $this->dropped = true;
            $this->connected = false;

            return;
        }

        if ($isApplicationRequest && $this->responses !== []) {
            $this->pending .= array_shift($this->responses);
        }
    }

    public function read(int $timeoutMs): string
    {
        if ($this->dropped) {
            throw new TransportException('ECR17: transport disconnected during exchange');
        }

        if ($this->pending === '') {
            // Simulate a blocking read that times out with no data.
            usleep(max(0, $timeoutMs) * 1000);

            return '';
        }

        $out = $this->pending;
        $this->pending = '';

        return $out;
    }

    // --- Test helpers ---------------------------------------------------------

    public function enqueueResponse(string $bytes): void
    {
        $this->responses[] = $bytes;
    }

    /** Make the next application-request send drop the connection. */
    public function disconnectOnNextRequest(): void
    {
        $this->disconnectOnRequest = true;
    }

    /** Simulate a successful reconnect. */
    public function rearm(): void
    {
        $this->disconnectOnRequest = false;
        $this->dropped = false;
        $this->connected = true;
    }

    /** @return list<string> */
    public function sentFrames(): array
    {
        return $this->sent;
    }

    public function applicationRequestCount(): int
    {
        return count(array_filter(
            $this->sent,
            static fn (string $f): bool => $f !== '' && ord($f[0]) === PacketCodec::STX,
        ));
    }
}
