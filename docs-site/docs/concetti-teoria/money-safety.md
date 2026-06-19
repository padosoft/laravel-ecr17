---
title: Money Safety
description: Why financial commands are not replayed after disconnect.
---

# Money Safety

Money safety is the central invariant: a financial command is never blindly re-sent after the connection drops.

::: callout danger "Invariant"
After a disconnect during `pay()`, `payExtended()`, `reverse()`, `preAuth()`, `incrementalAuth()`, `preAuthClosure()`, `verifyCard()`, `closeSession()`, `reprint()`, or `vas()`, surface the original failure and recover explicitly.
:::

## Safe retry boundary

| Safe after reconnect | Not safe after reconnect |
| --- | --- |
| `status()` | `pay()` |
| `totals()` | `payExtended()` |
| `sendLastResult()` | `reverse()` |
| `enableEcrPrinting()` | pre-auth mutations |

The rule is implemented in `Session\RetryPolicy` and enforced by client command calls.

## Worked example

```php
$job->attempt(function () use ($order) {
    try {
        return Ecr17::pay($order->amount_cents);
    } catch (Throwable $e) {
        return Ecr17::sendLastResult();
    }
});
```

::: callout warning "Queue retries"
Disable automatic re-execution of the payment body. Retry only the recovery path when you know it is idempotent for your order model.
:::
