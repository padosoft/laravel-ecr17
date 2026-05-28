<?php

declare(strict_types=1);

use Padosoft\Ecr17\Session\RetryPolicy;

// Money-critical: a financial command ($safe = false) must NEVER be retried,
// regardless of autoReconnect/drop state. Recovery is via sendLastResult ('G').
test('a financial command is never retried', function () {
    expect(RetryPolicy::shouldRetryAfterReconnect(true, true, false))->toBeFalse()
        ->and(RetryPolicy::shouldRetryAfterReconnect(false, true, false))->toBeFalse()
        ->and(RetryPolicy::shouldRetryAfterReconnect(true, false, false))->toBeFalse()
        ->and(RetryPolicy::shouldRetryAfterReconnect(false, false, false))->toBeFalse();
});

// A safe/idempotent command is retried ONLY when autoReconnect is on AND the
// transport actually dropped.
test('a safe command is retried only on reconnect after a drop', function () {
    expect(RetryPolicy::shouldRetryAfterReconnect(true, true, true))->toBeTrue()
        ->and(RetryPolicy::shouldRetryAfterReconnect(false, true, true))->toBeFalse()
        ->and(RetryPolicy::shouldRetryAfterReconnect(true, false, true))->toBeFalse()
        ->and(RetryPolicy::shouldRetryAfterReconnect(false, false, true))->toBeFalse();
});
