---
title: Production
description: Production usage recommendations.
---

# Production

## Use background execution

Payments can block for the full cardholder interaction window. Run them from Laravel Queue workers, Octane workers, or a dedicated service process.

::: callout warning "HTTP requests"
Do not block a PHP-FPM web request on a live POS payment. A 60-second terminal wait can exhaust request workers under load.
:::

## Persist state

Store every attempt with at least:

| Field | Reason |
| --- | --- |
| order id | Business correlation. |
| amount cents | Reconciliation. |
| command type | Recovery policy. |
| terminal id | Multi-terminal support. |
| STAN/auth code | Payment audit trail. |
| raw failure | Operations diagnosis. |

## Reconnect policy

Keep `auto_reconnect` enabled unless an operator is diagnosing a terminal or network problem.
