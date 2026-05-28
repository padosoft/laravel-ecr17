<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Session;

use Closure;
use Padosoft\Ecr17\Protocol\DecodedPacket;
use Padosoft\Ecr17\Protocol\PacketCodec;
use Padosoft\Ecr17\Protocol\PacketType;
use Padosoft\Ecr17\Transport\TransportInterface;
use RuntimeException;

/**
 * Drives one ECR17 request/response exchange over a Transport: frames the
 * request, performs the physical ACK/NAK handshake with retransmission, waits
 * for the application response while forwarding progress (SOH) and receipt ('S')
 * messages, and ACK/NAKs incoming frames per their LRC validity.
 *
 * Pure PHP and transport-agnostic (unit-tested against FakeTransport). Unlike the
 * RN core it is SYNCHRONOUS: it pumps Transport::read() instead of waiting on a
 * reader thread. A single exchange runs at a time.
 */
final class Ecr17Session
{
    private readonly PacketCodec $codec;

    private string $rxBuffer = '';

    /** An application result that arrived during the handshake (before/without ACK). */
    private ?DecodedPacket $pendingResult = null;

    private ?Closure $onProgress = null;

    private ?Closure $onReceiptLine = null;

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly SessionConfig $config,
    ) {
        $this->codec = new PacketCodec($config->lrcMode);
    }

    public function setOnProgress(?callable $cb): void
    {
        $this->onProgress = $cb === null ? null : Closure::fromCallable($cb);
    }

    public function setOnReceiptLine(?callable $cb): void
    {
        $this->onReceiptLine = $cb === null ? null : Closure::fromCallable($cb);
    }

    /**
     * Send a request and return the decoded application result. Throws on
     * retransmission exhaustion, ACK/response timeout, or transport disconnect.
     */
    public function exchange(string $requestPayload): DecodedPacket
    {
        $this->resetForNewTransaction();
        $this->ackHandshake($requestPayload);

        return $this->waitForResult();
    }

    /**
     * Like exchange(), but sends an extra additional-data message (command 'U',
     * tokenization) after the main request is ACKed, before the result.
     */
    public function exchangeWithAdditionalData(string $requestPayload, string $additionalPayload): DecodedPacket
    {
        $this->resetForNewTransaction();
        $this->ackHandshake($requestPayload);
        $this->ackHandshake($additionalPayload);

        return $this->waitForResult();
    }

    /** For commands whose only reply is the physical ACK (e.g. 'E', 'R'). */
    public function sendAckOnly(string $requestPayload): void
    {
        $this->resetForNewTransaction();
        $this->ackHandshake($requestPayload);
    }

    // --- Internals ------------------------------------------------------------

    private function resetForNewTransaction(): void
    {
        $this->rxBuffer = '';
        $this->pendingResult = null;
    }

    private function ackHandshake(string $requestPayload): void
    {
        $requestFrame = $this->codec->encodeApplication($requestPayload);
        $this->transport->send($requestFrame);

        $attempts = 1;
        $deadline = $this->nowMs() + $this->config->ackTimeoutMs;

        while (true) {
            $remaining = $deadline - $this->nowMs();
            if ($remaining <= 0) {
                if ($attempts > $this->config->retryCount) {
                    throw new RuntimeException("ECR17: no ACK after {$attempts} attempts");
                }
                $this->sleepMs($this->config->retryDelayMs);
                $this->transport->send($requestFrame);
                $attempts++;
                $deadline = $this->nowMs() + $this->config->ackTimeoutMs;

                continue;
            }

            $pkt = $this->waitForFrame($remaining);
            if ($pkt === null) {
                continue;
            }
            if ($pkt->type === PacketType::Ack) {
                return;
            }
            if ($pkt->type === PacketType::Nak) {
                if ($attempts > $this->config->retryCount) {
                    throw new RuntimeException("ECR17: NAK after {$attempts} attempts");
                }
                $this->sleepMs($this->config->retryDelayMs);
                $this->transport->send($requestFrame);
                $attempts++;
                $deadline = $this->nowMs() + $this->config->ackTimeoutMs;

                continue;
            }
            if ($pkt->type === PacketType::Application) {
                // The terminal sent the result without (or before) a physical ACK.
                // Don't drop it: stash for waitForResult() to validate/ACK.
                $this->pendingResult = $pkt;

                return;
            }
            // Ignore any progress frames that may precede the ACK.
        }
    }

    private function waitForResult(): DecodedPacket
    {
        $deadline = $this->nowMs() + $this->config->responseTimeoutMs;

        while (true) {
            if ($this->pendingResult !== null) {
                $pkt = $this->pendingResult;
                $this->pendingResult = null;
            } else {
                $remaining = $deadline - $this->nowMs();
                if ($remaining <= 0) {
                    throw new RuntimeException('ECR17: no application response before timeout');
                }
                $pkt = $this->waitForFrame($remaining);
                if ($pkt === null) {
                    continue;
                }
            }

            switch ($pkt->type) {
                case PacketType::Progress:
                    if ($this->onProgress !== null) {
                        ($this->onProgress)($pkt->payload);
                    }
                    break;
                case PacketType::Application:
                    if (! $pkt->validLrc) {
                        $this->sendControl(PacketCodec::NAK);
                        break;
                    }
                    $this->sendControl(PacketCodec::ACK);
                    if (self::isReceipt($pkt->payload)) {
                        if ($this->onReceiptLine !== null) {
                            ($this->onReceiptLine)($pkt->payload);
                        }
                        break;
                    }
                    $this->drainReceipts();

                    return $pkt;
                case PacketType::Ack:
                case PacketType::Nak:
                    break; // stray confirmation; ignore
                case PacketType::Unknown:
                    $this->sendControl(PacketCodec::NAK);
                    break;
            }
        }
    }

    private function drainReceipts(): void
    {
        if ($this->config->receiptDrainMs <= 0) {
            return;
        }
        while (true) {
            $pkt = $this->waitForFrame($this->config->receiptDrainMs);
            if ($pkt === null) {
                return; // idle: no more receipts
            }
            switch ($pkt->type) {
                case PacketType::Application:
                    if ($pkt->validLrc) {
                        $this->sendControl(PacketCodec::ACK);
                        if (self::isReceipt($pkt->payload) && $this->onReceiptLine !== null) {
                            ($this->onReceiptLine)($pkt->payload);
                        }
                    } else {
                        $this->sendControl(PacketCodec::NAK);
                    }
                    break;
                case PacketType::Progress:
                    if ($this->onProgress !== null) {
                        ($this->onProgress)($pkt->payload);
                    }
                    break;
                default:
                    break;
            }
        }
    }

    /** Blocks up to $timeoutMs for one complete frame; null on timeout. */
    private function waitForFrame(int $timeoutMs): ?DecodedPacket
    {
        $deadline = $this->nowMs() + $timeoutMs;
        while (true) {
            $frame = $this->extractFrame();
            if ($frame !== null) {
                return $this->codec->decode($frame);
            }
            $remaining = $deadline - $this->nowMs();
            if ($remaining <= 0) {
                return null;
            }
            // May throw TransportException on a dropped connection — propagates as
            // the "disconnected during exchange" failure to the caller.
            $chunk = $this->transport->read($remaining);
            if ($chunk !== '') {
                $this->rxBuffer .= $chunk;
            }
        }
    }

    /**
     * Extracts one complete frame from the front of the RX buffer, dropping
     * leading junk bytes to resynchronise. Returns null if none is complete yet.
     */
    private function extractFrame(): ?string
    {
        while ($this->rxBuffer !== '') {
            $first = ord($this->rxBuffer[0]);

            if ($first === PacketCodec::ACK || $first === PacketCodec::NAK) {
                if (strlen($this->rxBuffer) < 3) {
                    return null; // wait for ETX + LRC
                }
                $frame = substr($this->rxBuffer, 0, 3);
                $this->rxBuffer = substr($this->rxBuffer, 3);

                return $frame;
            }

            if ($first === PacketCodec::STX) {
                $etx = strpos($this->rxBuffer, chr(PacketCodec::ETX));
                if ($etx === false || $etx + 1 >= strlen($this->rxBuffer)) {
                    return null; // wait for ETX and the trailing LRC
                }
                $frame = substr($this->rxBuffer, 0, $etx + 2); // STX..ETX + LRC
                $this->rxBuffer = substr($this->rxBuffer, $etx + 2);

                return $frame;
            }

            if ($first === PacketCodec::SOH) {
                $eot = strpos($this->rxBuffer, chr(PacketCodec::EOT));
                if ($eot === false) {
                    return null; // wait for EOT
                }
                $frame = substr($this->rxBuffer, 0, $eot + 1);
                $this->rxBuffer = substr($this->rxBuffer, $eot + 1);

                return $frame;
            }

            // Unrecognised lead byte: drop it and resynchronise.
            $this->rxBuffer = substr($this->rxBuffer, 1);
        }

        return null;
    }

    private function sendControl(int $control): void
    {
        $this->transport->send($this->codec->encodeControl($control));
    }

    private static function isReceipt(string $payload): bool
    {
        // Send-ticket message uses message code 'S' at position 10 (index 9).
        return strlen($payload) >= 10 && $payload[9] === 'S';
    }

    private function nowMs(): int
    {
        return (int) (hrtime(true) / 1_000_000);
    }

    private function sleepMs(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
