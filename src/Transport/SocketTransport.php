<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Transport;

/**
 * Real TCP transport over a non-blocking PHP stream socket. The Laravel server
 * must be able to reach the POS terminal on the LAN. Not unit-tested (needs a
 * real socket); the protocol logic is covered via FakeTransport.
 */
final class SocketTransport implements TransportInterface
{
    /** @var resource|null */
    private $stream = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $connectTimeoutMs = 5000,
    ) {}

    public function connect(): void
    {
        $this->disconnect();

        if ($this->host === '') {
            throw new TransportException('ECR17: host is empty');
        }

        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            max(0.1, $this->connectTimeoutMs / 1000),
            STREAM_CLIENT_CONNECT,
        );

        if ($stream === false) {
            throw new TransportException(
                "ECR17: connect to {$this->host}:{$this->port} failed: {$errstr} ({$errno})"
            );
        }

        stream_set_blocking($stream, false);
        $this->stream = $stream;
    }

    public function disconnect(): void
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }
        $this->stream = null;
    }

    public function isConnected(): bool
    {
        return is_resource($this->stream) && ! feof($this->stream);
    }

    public function send(string $bytes): void
    {
        if (! is_resource($this->stream)) {
            throw new TransportException('ECR17: transport is not connected');
        }

        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = @fwrite($this->stream, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                if (feof($this->stream)) {
                    throw new TransportException('ECR17: transport disconnected during send');
                }
                // Socket buffer full: yield briefly and retry.
                usleep(1000);

                continue;
            }
            $offset += $written;
        }
    }

    public function read(int $timeoutMs): string
    {
        if (! is_resource($this->stream)) {
            throw new TransportException('ECR17: transport is not connected');
        }

        $read = [$this->stream];
        $write = null;
        $except = null;
        $sec = intdiv($timeoutMs, 1000);
        $usec = ($timeoutMs % 1000) * 1000;

        $ready = @stream_select($read, $write, $except, $sec, $usec);
        if ($ready === false) {
            throw new TransportException('ECR17: stream_select failed');
        }
        if ($ready === 0) {
            return ''; // timeout, no data
        }

        $chunk = @fread($this->stream, 4096);
        if ($chunk === false || ($chunk === '' && feof($this->stream))) {
            throw new TransportException('ECR17: transport disconnected during exchange');
        }

        return $chunk;
    }
}
