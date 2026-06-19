---
title: Observability
description: Logs, callbacks, and operational signals.
---

# Observability

The client exposes callbacks for progress, receipt lines, and connection state changes.

```php
$client->setOnProgress(fn (string $message) => Log::info('ECR17 progress', ['message' => $message]));
$client->setOnReceiptLine(fn (string $line) => ReceiptBuffer::append($order, $line));
$client->setOnConnectionStateChange(fn (string $state) => Metrics::increment("ecr17.$state"));
```

## Signals to track

| Signal | Use |
| --- | --- |
| Connection attempts | Network stability. |
| ACK timeouts | Terminal or frame-level problems. |
| Response timeouts | Cardholder or POS delays. |
| Recovery calls | Payment uncertainty rate. |
| NAK count | Protocol configuration drift. |

::: callout tip "Redaction"
Normalize and redact payload logs before sending them to central observability systems.
:::
