---
title: Tokenization
description: Tokenization request support.
---

# Tokenization

Payments, pre-authorizations, and card verification can include a `TokenizationRequest`.

```php
use Padosoft\Ecr17\TokenizationRequest;
use Padosoft\Ecr17\TokenizationService;

$tokenization = new TokenizationRequest(
    service: TokenizationService::Recurring,
    contractCode: 'CONTRACT-42',
);

$result = Ecr17::pay(
    amountCents: 1000,
    paymentType: 'credit',
    tokenization: $tokenization,
);
```

The client sends the main request, waits for ACK, then sends the additional `U` data before waiting for the final result.

::: callout info "Contract code"
Treat the contract code as a durable business identifier. Store it with the customer mandate or subscription record, not only with a single payment attempt.
:::
