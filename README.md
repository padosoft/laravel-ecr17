<div align="center">

# 💳 laravel-ecr17

**Drive Italian ECR17 / "Protocollo 17" POS terminals (Nexi Group) over TCP — straight from your Laravel app.**

**The most complete open-source ECR17 toolkit for PHP & Laravel.**

[![Packagist Version](https://img.shields.io/packagist/v/padosoft/laravel-ecr17.svg?style=flat-square)](https://packagist.org/packages/padosoft/laravel-ecr17)
[![Tests](https://github.com/padosoft/laravel-ecr17/actions/workflows/tests.yml/badge.svg)](https://github.com/padosoft/laravel-ecr17/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/packagist/l/padosoft/laravel-ecr17.svg?style=flat-square)](LICENSE)
[![PHP](https://img.shields.io/packagist/php-v/padosoft/laravel-ecr17.svg?style=flat-square)](composer.json)

</div>

> 📱 **Building for iOS / Android (React Native)?** There's a sibling port:
> **[padosoft/react-native-ecr17-protocol](https://github.com/padosoft/react-native-ecr17-protocol)** —
> the same ECR17 protocol as a React Native / Nitro module. It is the behavioral
> source of truth; this PHP package mirrors its protocol core and test suite.

---

## 📚 Table of contents

- [What is ECR17?](#-what-is-ecr17)
- [Highlights](#-highlights)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Quick start](#-quick-start)
- [Configuration](#-configuration)
- [Commands](#-commands)
- [Events](#-events)
- [Money safety](#-money-safety)
- [Connection handling](#-connection-handling)
- [Demo debug console](#-demo-debug-console)
- [Production usage](#-production-usage)
- [Testing](#-testing)
- [License](#-license)

## 💡 What is ECR17?

**ECR17** ("Protocollo 17") is the Italian amount-exchange protocol spoken between
a cash register (ECR) and a payment terminal (POS) over TCP/IP, supported by
**Nexi Group** terminals. The ECR frames an application message
(`STX … ETX LRC`), the terminal ACK/NAKs it, streams progress and receipt lines,
and returns the transaction result. This package implements the full protocol —
framing, LRC, the ACK/NAK handshake with retransmission and timeouts, every
command builder and response parser — as a clean, framework-free PHP core wrapped
in a thin Laravel layer.

## ✨ Highlights

- **Framework-free protocol core** (`Padosoft\Ecr17\Protocol|Response|Session`) —
  pure PHP, unit-tested in isolation.
- **Every ECR17 command**: status, pay, extended pay, reverse, pre-auth,
  incremental, pre-auth closure, card verification, close session, totals,
  send-last-result, ECR printing, reprint, VAS, plus tokenization (`U`).
- 💰 **Money-safe by design** — a financial command is **never** blindly re-sent
  after a drop (no double-charge); recover via `sendLastResult()` (`G`).
- **Proactive reconnection** — a stale (peer-closed) socket is detected and
  reconnected *before* sending, so a payment never starts on a dead socket.
- **Progress & receipt streaming** via events/callbacks.
- **Tested against real scripted scenarios** (Pest), ported 1:1 from the React
  Native sibling's GoogleTest suite.

## ✅ Requirements

- **PHP 8.3+**, **Laravel 13** (PHP 8.3–8.5).
- The PHP server must be able to reach the POS terminal over **TCP on the LAN**.

## 📦 Installation

```bash
composer require padosoft/laravel-ecr17
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=ecr17-config
```

## 🚀 Quick start

```php
use Padosoft\Ecr17\Facades\Ecr17;

// Configure via config/ecr17.php (or .env), then:
Ecr17::connect();

$result = Ecr17::pay(amountCents: 1000, paymentType: 'credit'); // €10.00

if ($result->outcome === \Padosoft\Ecr17\Response\Outcome::Ok) {
    // $result->authCode, $result->pan, $result->stan, ...
}
```

Or build a client explicitly (e.g. with your own transport):

```php
use Padosoft\Ecr17\Ecr17Client;
use Padosoft\Ecr17\Ecr17Config;
use Padosoft\Ecr17\Transport\SocketTransport;

$config = new Ecr17Config(host: '192.168.1.50', port: 10000, terminalId: '12345678', cashRegisterId: '1');
$client = new Ecr17Client(new SocketTransport($config->host, $config->port, $config->connectionTimeoutMs), $config);

$status = $client->status();
```

## ⚙️ Configuration

`config/ecr17.php` (all keys overridable via `.env`):

| Key | Env | Default | Notes |
| --- | --- | --- | --- |
| `host` | `ECR17_HOST` | `''` | POS terminal IP |
| `port` | `ECR17_PORT` | `1024` | TCP port |
| `terminal_id` | `ECR17_TERMINAL_ID` | `''` | 8-char terminal id |
| `cash_register_id` | `ECR17_CASH_REGISTER_ID` | `''` | ECR id |
| `lrc_mode` | `ECR17_LRC_MODE` | `std` | `stx \| std \| noext \| stx_noext` |
| `auto_reconnect` | `ECR17_AUTO_RECONNECT` | `true` | reconnect + retry safe ops on drop |
| `connection_timeout_ms` | `ECR17_CONNECTION_TIMEOUT_MS` | `5000` | |
| `response_timeout_ms` | `ECR17_RESPONSE_TIMEOUT_MS` | `60000` | cardholder wait |
| `ack_timeout_ms` | `ECR17_ACK_TIMEOUT_MS` | `2000` | |
| `retry_count` / `retry_delay_ms` | … | `3` / `200` | retransmissions |
| `receipt_drain_ms` | `ECR17_RECEIPT_DRAIN_MS` | `0` | keep forwarding `S` lines after the result |

## 🧾 Commands

`status()`, `pay()`, `payExtended()`, `reverse()`, `preAuth()`,
`incrementalAuth()`, `preAuthClosure()`, `verifyCard()`, `closeSession()`,
`totals()`, `sendLastResult()`, `enableEcrPrinting()`, `reprint()`, `vas()`.

Payments/pre-auth/verify accept an optional `TokenizationRequest` (`U`).

## 📡 Events

Wire callbacks for real-time updates:

```php
$client->setOnProgress(fn (string $msg) => /* "INSERIRE CARTA" ... */);
$client->setOnReceiptLine(fn (string $line) => /* receipt text */);
$client->setOnConnectionStateChange(fn (string $state) => /* connecting|connected|disconnected */);
```

## 💰 Money safety

A connection can drop after the terminal has charged the card but before the
response arrives. Re-sending the request would **double-charge**. Therefore a
financial command is **never** retried after a drop — only read-only/idempotent
commands (`status`, `totals`, `sendLastResult`, `enableEcrPrinting`) are. To
recover a lost result, call `sendLastResult()` (command `G`). This invariant lives
in `Session\RetryPolicy` and is locked by its tests.

## 🔌 Connection handling

ECR17/Nexi terminals often close the TCP socket between transactions. The client
runs a **non-destructive liveness probe** before each command and reconnects
proactively, so a command never starts on a stale, half-open socket (which would
otherwise fail and — correctly — refuse a financial retry).

## 🖥️ Demo debug console

The `demo/` directory contains a small Laravel app with a **React + Tailwind**
debug console (AJAX): configure & connect to a POS, run every command, and watch
the behind-the-scenes log (on screen + file). See `demo/README.md`.

## 🏭 Production usage

The demo runs commands **synchronously** for simplicity. A payment can block up to
`response_timeout_ms` (~60s) while the cardholder interacts — that would tie up a
PHP-FPM worker. **In production**, drive the package from a **queued job** (Laravel
Queue + worker) and poll/push the result, or run it under **Octane/Swoole**. Never
block a web request on a live payment.

## 🧪 Testing

```bash
composer test     # Pest
composer lint     # Pint --test
composer analyse  # PHPStan
```

## 📄 License

MIT © [padosoft](https://github.com/padosoft)
