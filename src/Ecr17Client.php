<?php

declare(strict_types=1);

namespace Padosoft\Ecr17;

use Closure;
use Padosoft\Ecr17\Protocol\DecodedPacket;
use Padosoft\Ecr17\Protocol\Ecr17Protocol;
use Padosoft\Ecr17\Response\CloseResponse;
use Padosoft\Ecr17\Response\Ecr17Response;
use Padosoft\Ecr17\Response\PaymentResponse;
use Padosoft\Ecr17\Response\PreAuthResponse;
use Padosoft\Ecr17\Response\StatusResponse;
use Padosoft\Ecr17\Response\TotalsResponse;
use Padosoft\Ecr17\Response\VasResponse;
use Padosoft\Ecr17\Session\Ecr17Session;
use Padosoft\Ecr17\Session\RetryPolicy;
use Padosoft\Ecr17\Transport\TransportInterface;
use Throwable;

/**
 * High-level ECR17 client: configure → connect → run commands, each a full
 * request/response exchange. Synchronous (blocks up to responseTimeoutMs while
 * the cardholder interacts). Emits progress/receipt/connection callbacks.
 *
 * MONEY-SAFETY: financial commands are never blindly re-sent after a drop; only
 * read-only/idempotent commands may be retried (see RetryPolicy). Recover a lost
 * result via sendLastResult() ('G').
 */
final class Ecr17Client
{
    private readonly Ecr17Session $session;

    private ?Closure $onConnectionStateChange = null;

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly Ecr17Config $config,
    ) {
        $this->session = new Ecr17Session($transport, $config->toSessionConfig());
    }

    public function setOnProgress(?callable $cb): void
    {
        $this->session->setOnProgress($cb);
    }

    public function setOnReceiptLine(?callable $cb): void
    {
        $this->session->setOnReceiptLine($cb);
    }

    public function setOnConnectionStateChange(?callable $cb): void
    {
        $this->onConnectionStateChange = $cb === null ? null : Closure::fromCallable($cb);
    }

    // --- Connection -----------------------------------------------------------

    public function connect(): void
    {
        $this->ensureConnected();
    }

    public function disconnect(): void
    {
        $this->transport->disconnect();
        $this->emitState('disconnected');
    }

    public function isConnected(): bool
    {
        return $this->transport->isConnected();
    }

    // --- Commands -------------------------------------------------------------

    public function status(): StatusResponse
    {
        $this->ensureConnected();
        $pkt = $this->runTransaction(
            Ecr17Protocol::buildStatusMessage($this->config->terminalId),
            null,
            safeToRetry: true,
        );

        return Ecr17Response::parseStatus($pkt->payload);
    }

    public function pay(int $amountCents, string $paymentType = 'auto', bool $cardAlreadyPresent = false, ?string $cashRegisterId = null, string $receiptText = '', ?TokenizationRequest $tokenization = null): PaymentResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildPaymentMessage(
            $this->config->terminalId,
            $this->cashRegisterIdOr($cashRegisterId),
            $amountCents,
            self::mapPaymentType($paymentType),
            $cardAlreadyPresent,
            $tokenization !== null,
            $receiptText,
        );
        $pkt = $this->runTransaction($payload, $tokenization, safeToRetry: false);

        return Ecr17Response::parsePayment($pkt->payload);
    }

    public function payExtended(int $amountCents, string $paymentType = 'auto', bool $cardAlreadyPresent = false, ?string $cashRegisterId = null, string $receiptText = '', ?TokenizationRequest $tokenization = null): PaymentResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildExtendedPaymentMessage(
            $this->config->terminalId,
            $this->cashRegisterIdOr($cashRegisterId),
            $amountCents,
            self::mapPaymentType($paymentType),
            $cardAlreadyPresent,
            $tokenization !== null,
            $receiptText,
        );
        $pkt = $this->runTransaction($payload, $tokenization, safeToRetry: false);

        return Ecr17Response::parsePayment($pkt->payload);
    }

    public function reverse(?string $stan = null, ?string $cashRegisterId = null): PaymentResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildReversalMessage(
            $this->config->terminalId,
            $this->cashRegisterIdOr($cashRegisterId),
            $stan ?? '000000',
        );
        $pkt = $this->runTransaction($payload, null, safeToRetry: false);

        return Ecr17Response::parsePayment($pkt->payload);
    }

    public function preAuth(int $amountCents, string $paymentType = 'auto', bool $cardAlreadyPresent = false, ?string $cashRegisterId = null, string $receiptText = '', ?TokenizationRequest $tokenization = null): PreAuthResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildPreAuthMessage(
            $this->config->terminalId,
            $this->cashRegisterIdOr($cashRegisterId),
            $amountCents,
            self::mapPaymentType($paymentType),
            $cardAlreadyPresent,
            $tokenization !== null,
            $receiptText,
        );
        $pkt = $this->runTransaction($payload, $tokenization, safeToRetry: false);

        return Ecr17Response::parsePreAuth($pkt->payload);
    }

    public function incrementalAuth(int $amountCents, string $originalPreAuthCode, ?string $cashRegisterId = null, string $receiptText = ''): PreAuthResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildIncrementalMessage(
            $this->config->terminalId,
            $this->cashRegisterIdOr($cashRegisterId),
            $amountCents,
            $originalPreAuthCode,
            false,
            $receiptText,
        );
        $pkt = $this->runTransaction($payload, null, safeToRetry: false);

        return Ecr17Response::parsePreAuth($pkt->payload);
    }

    public function preAuthClosure(int $amountCents, string $originalPreAuthCode, ?string $cashRegisterId = null, string $receiptText = ''): PaymentResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildPreAuthClosureMessage(
            $this->config->terminalId,
            $this->cashRegisterIdOr($cashRegisterId),
            $amountCents,
            $originalPreAuthCode,
            false,
            $receiptText,
        );
        $pkt = $this->runTransaction($payload, null, safeToRetry: false);

        return Ecr17Response::parsePayment($pkt->payload);
    }

    public function verifyCard(string $paymentType = 'auto', ?string $cashRegisterId = null, ?TokenizationRequest $tokenization = null): PaymentResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildCardVerificationMessage(
            $this->config->terminalId,
            $this->cashRegisterIdOr($cashRegisterId),
            self::mapPaymentType($paymentType),
            $tokenization !== null,
        );
        $pkt = $this->runTransaction($payload, $tokenization, safeToRetry: false);

        return Ecr17Response::parsePayment($pkt->payload);
    }

    public function closeSession(?string $cashRegisterId = null): CloseResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildCloseSessionMessage(
            $this->config->terminalId,
            $this->cashRegisterIdOr($cashRegisterId),
        );
        $pkt = $this->runTransaction($payload, null, safeToRetry: false);

        return Ecr17Response::parseClose($pkt->payload);
    }

    public function totals(?string $cashRegisterId = null): TotalsResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildTotalsMessage(
            $this->config->terminalId,
            $this->cashRegisterIdOr($cashRegisterId),
        );
        $pkt = $this->runTransaction($payload, null, safeToRetry: true);

        return Ecr17Response::parseTotals($pkt->payload);
    }

    public function sendLastResult(?string $cashRegisterId = null): PaymentResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildSendLastResultMessage(
            $this->config->terminalId,
            $this->cashRegisterIdOr($cashRegisterId),
        );
        $pkt = $this->runTransaction($payload, null, safeToRetry: true);

        return Ecr17Response::parsePayment($pkt->payload);
    }

    public function enableEcrPrinting(bool $enabled): void
    {
        $this->ensureConnected();
        $this->runAckOnly(
            Ecr17Protocol::buildEnableEcrPrintMessage($this->config->terminalId, $enabled),
            safeToRetry: true,
        );
    }

    public function reprint(bool $toEcr): void
    {
        $this->ensureConnected();
        $this->runAckOnly(
            Ecr17Protocol::buildReprintMessage($this->config->terminalId, $toEcr),
            safeToRetry: false,
        );
    }

    public function vas(string $xmlRequest): VasResponse
    {
        $this->ensureConnected();
        $payload = Ecr17Protocol::buildVasMessage(
            $this->config->terminalId,
            $this->config->cashRegisterId,
            $xmlRequest,
        );
        $pkt = $this->runTransaction($payload, null, safeToRetry: false);

        return Ecr17Response::parseVas($pkt->payload);
    }

    // --- Internals ------------------------------------------------------------

    private function ensureConnected(): void
    {
        // PROACTIVE: reconnect if the socket is gone OR half-open (peer closed
        // between transactions). ECR17 terminals often close the TCP after each
        // transaction, and isConnected() alone can't see a half-open socket — so a
        // financial command would otherwise be sent on a dead socket, fail, and be
        // (correctly) refused a retry. Probing liveness here means each command
        // starts on a verified-live socket.
        if ($this->transport->isConnected() && $this->transport->isAlive()) {
            return;
        }
        $this->emitState('connecting');
        try {
            $this->transport->connect();
        } catch (Throwable $e) {
            $this->emitState('disconnected');
            throw $e;
        }
        $this->emitState('connected');
    }

    private function runTransaction(string $payload, ?TokenizationRequest $tokenization, bool $safeToRetry): DecodedPacket
    {
        $doExchange = function () use ($payload, $tokenization): DecodedPacket {
            if ($tokenization !== null) {
                $recurring = $tokenization->service === TokenizationService::Recurring;
                $tag = Ecr17Protocol::formatTokenizationTag($recurring, $tokenization->contractCode);
                $additional = Ecr17Protocol::buildAdditionalTagsMessage($this->config->terminalId, $tag);

                return $this->session->exchangeWithAdditionalData($payload, $additional);
            }

            return $this->session->exchange($payload);
        };

        try {
            return $doExchange();
        } catch (Throwable $original) {
            $dropped = ! $this->transport->isConnected();
            if ($this->config->autoReconnect && $dropped) {
                try {
                    $this->ensureConnected(); // restore the socket for subsequent commands
                } catch (Throwable) {
                    throw $original; // surface the original exchange error
                }
            }
            if (RetryPolicy::shouldRetryAfterReconnect($this->config->autoReconnect, $dropped, $safeToRetry)) {
                return $doExchange(); // only read-only/idempotent ops may be replayed
            }
            throw $original; // financial op: surface the error (recover via sendLastResult / 'G')
        }
    }

    private function runAckOnly(string $payload, bool $safeToRetry): void
    {
        try {
            $this->session->sendAckOnly($payload);
        } catch (Throwable $original) {
            $dropped = ! $this->transport->isConnected();
            if ($this->config->autoReconnect && $dropped) {
                try {
                    $this->ensureConnected();
                } catch (Throwable) {
                    throw $original;
                }
            }
            if (! RetryPolicy::shouldRetryAfterReconnect($this->config->autoReconnect, $dropped, $safeToRetry)) {
                throw $original;
            }
            $this->session->sendAckOnly($payload);
        }
    }

    private function cashRegisterIdOr(?string $override): string
    {
        return $override ?? $this->config->cashRegisterId;
    }

    private function emitState(string $state): void
    {
        if ($this->onConnectionStateChange !== null) {
            ($this->onConnectionStateChange)($state);
        }
    }

    private static function mapPaymentType(string $type): string
    {
        return match ($type) {
            'debit' => '1',
            'credit' => '2',
            'other' => '3',
            default => '0', // auto
        };
    }
}
