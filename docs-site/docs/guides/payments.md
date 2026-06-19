---
title: Payments
description: Payment command workflow.
---

# Payments

Use `pay()` for the common sale path and `payExtended()` when the terminal integration requires the extended command variant.

```php
$result = $client->pay(
    amountCents: 2590,
    paymentType: 'auto',
    cardAlreadyPresent: false,
    cashRegisterId: null,
    receiptText: 'ORDER 1042',
);
```

## Payment type

| Value | Protocol value | Meaning |
| --- | --- | --- |
| `auto` | `0` | Let terminal choose. |
| `debit` | `1` | Debit flow. |
| `credit` | `2` | Credit flow. |
| `other` | `3` | Other supported type. |

::: callout warning "No blind replay"
If `pay()` fails because the connection dropped, the package intentionally throws instead of replaying the payment. Use `sendLastResult()` to recover the terminal result.
:::

## Worked example

```php
try {
    $payment = Ecr17::pay(1599, receiptText: 'ORDER A-100');
    OrderPayment::recordFromEcr17($order, $payment);
} catch (Throwable $error) {
    $last = Ecr17::sendLastResult();
    OrderPayment::reconcilePossiblePayment($order, $last);
}
```
