---
title: API
description: Ecr17Client API reference.
---

# API

## Connection

| Method | Return | Notes |
| --- | --- | --- |
| `connect()` | `void` | Ensures transport connection. |
| `disconnect()` | `void` | Closes transport. |
| `isConnected()` | `bool` | Transport state check. |

## Commands

| Method | Return |
| --- | --- |
| `status()` | `StatusResponse` |
| `pay(int $amountCents, string $paymentType = 'auto', bool $cardAlreadyPresent = false, ?string $cashRegisterId = null, string $receiptText = '', ?TokenizationRequest $tokenization = null)` | `PaymentResponse` |
| `payExtended(...)` | `PaymentResponse` |
| `reverse(?string $stan = null, ?string $cashRegisterId = null)` | `PaymentResponse` |
| `preAuth(...)` | `PreAuthResponse` |
| `incrementalAuth(int $amountCents, string $originalPreAuthCode, ?string $cashRegisterId = null, string $receiptText = '')` | `PreAuthResponse` |
| `preAuthClosure(...)` | `PaymentResponse` |
| `verifyCard(...)` | `PaymentResponse` |
| `closeSession(?string $cashRegisterId = null)` | `CloseResponse` |
| `totals(?string $cashRegisterId = null)` | `TotalsResponse` |
| `sendLastResult(?string $cashRegisterId = null)` | `PaymentResponse` |
| `enableEcrPrinting(bool $enabled)` | `void` |
| `reprint(bool $toEcr)` | `void` |
| `vas(string $xmlRequest)` | `VasResponse` |

## Callbacks

| Method | Callback payload |
| --- | --- |
| `setOnProgress(?callable $cb)` | Terminal progress string. |
| `setOnReceiptLine(?callable $cb)` | Receipt line string. |
| `setOnConnectionStateChange(?callable $cb)` | `connecting`, `connected`, or `disconnected`. |
