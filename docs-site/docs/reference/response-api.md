---
title: Response API
description: Response objects and parsing.
---

# Response API

`Ecr17Response` parses raw application payloads into typed response objects.

| Parser | Return |
| --- | --- |
| `parsePayment()` | `PaymentResponse` |
| `parsePreAuth()` | `PreAuthResponse` |
| `parseStatus()` | `StatusResponse` |
| `parseTotals()` | `TotalsResponse` |
| `parseClose()` | `CloseResponse` |
| `parseVas()` | `VasResponse` |

## Common pattern

```php
use Padosoft\Ecr17\Response\Outcome;

if ($response->outcome === Outcome::Ok) {
    // Persist successful terminal result fields.
}
```

::: callout warning "Unknown fields"
Terminal firmware may vary. Keep raw diagnostic context during rollout, but redact sensitive payment data.
:::
