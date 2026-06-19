---
title: Quickstart
description: First working laravel-ecr17 payment flow.
---

# Quickstart

::: steps
1. Install the package.

   ```bash
   composer require padosoft/laravel-ecr17
   ```

2. Publish the Laravel config when you want a local editable copy.

   ```bash
   php artisan vendor:publish --tag=ecr17-config
   ```

3. Add POS connection details to `.env`.

   ```dotenv
   ECR17_HOST=192.168.1.50
   ECR17_PORT=1024
   ECR17_TERMINAL_ID=12345678
   ECR17_CASH_REGISTER_ID=1
   ```

4. Run a payment from application code.

   ```php
   use Padosoft\Ecr17\Facades\Ecr17;
   use Padosoft\Ecr17\Response\Outcome;

   Ecr17::connect();

   $result = Ecr17::pay(amountCents: 1000, paymentType: 'credit');

   if ($result->outcome === Outcome::Ok) {
       // Store authorization fields and move the order forward.
   }
   ```
:::

::: tabs
== tab Facade

Use the facade in normal Laravel code when the configured default POS is enough.

```php
$status = Ecr17::status();
```

== tab Explicit client

Use an explicit client for tests, multiple terminals, or custom transport.

```php
use Padosoft\Ecr17\Ecr17Client;
use Padosoft\Ecr17\Ecr17Config;
use Padosoft\Ecr17\Transport\SocketTransport;

$config = new Ecr17Config(
    host: '192.168.1.50',
    port: 1024,
    terminalId: '12345678',
    cashRegisterId: '1',
);

$client = new Ecr17Client(
    new SocketTransport($config->host, $config->port, $config->connectionTimeoutMs),
    $config,
);
```
:::

## Recovery checkpoint

If the socket drops during a payment, do not call `pay()` again for the same order. Call `sendLastResult()` and reconcile the returned STAN/auth data with your order state.
