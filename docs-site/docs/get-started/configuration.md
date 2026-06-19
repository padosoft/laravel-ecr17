---
title: Configuration
description: Runtime configuration keys for ECR17 sessions.
---

# Configuration

`config/ecr17.php` maps environment variables into `Ecr17Config`.

| Key | Environment variable | Default | Purpose |
| --- | --- | --- | --- |
| `host` | `ECR17_HOST` | empty | POS IP address. |
| `port` | `ECR17_PORT` | `1024` | POS TCP port. |
| `terminal_id` | `ECR17_TERMINAL_ID` | empty | Eight-character terminal id. |
| `cash_register_id` | `ECR17_CASH_REGISTER_ID` | empty | Cash register id sent in financial messages. |
| `lrc_mode` | `ECR17_LRC_MODE` | `std` | LRC scope: `stx`, `std`, `noext`, `stx_noext`. |
| `keep_alive` | `ECR17_KEEP_ALIVE` | `true` | Transport keep-alive preference. |
| `auto_reconnect` | `ECR17_AUTO_RECONNECT` | `true` | Reconnect before commands and after safe drops. |
| `connection_timeout_ms` | `ECR17_CONNECTION_TIMEOUT_MS` | `5000` | Socket connection timeout. |
| `response_timeout_ms` | `ECR17_RESPONSE_TIMEOUT_MS` | `60000` | Wait time for terminal result. |
| `ack_timeout_ms` | `ECR17_ACK_TIMEOUT_MS` | `2000` | Physical ACK wait window. |
| `retry_count` | `ECR17_RETRY_COUNT` | `3` | ACK/NAK retransmission attempts. |
| `retry_delay_ms` | `ECR17_RETRY_DELAY_MS` | `200` | Delay between retransmissions. |
| `receipt_drain_ms` | `ECR17_RECEIPT_DRAIN_MS` | `0` | Extra receipt-line drain window after result. |
| `debug` | `ECR17_DEBUG` | `false` | App-level debug flag. |

::: callout warning "Timeout sizing"
`response_timeout_ms` must cover the real cardholder interaction window. Do not reduce it to HTTP-request-friendly values; move the command into a worker instead.
:::
