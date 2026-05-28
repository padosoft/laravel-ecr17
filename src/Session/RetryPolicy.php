<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Session;

/**
 * Decides whether a command may be safely RE-SENT after an auto-reconnect.
 *
 * ⚠️ MONEY-CRITICAL INVARIANT: a financial command ($safeToRetry === false) must
 * NEVER be retried. If the connection drops after the terminal processed the
 * payment but before the response arrives, a blind re-send would charge the
 * cardholder twice. Recover such cases via sendLastResult ('G'), NOT a re-send.
 *
 * Only read-only / idempotent commands (status, totals, sendLastResult,
 * enable-printing) pass $safeToRetry === true. Reconnecting the socket is a
 * separate, always-safe action; this only governs whether the *request* is replayed.
 */
final class RetryPolicy
{
    public static function shouldRetryAfterReconnect(bool $autoReconnect, bool $transportDropped, bool $safeToRetry): bool
    {
        return $autoReconnect && $transportDropped && $safeToRetry;
    }
}
