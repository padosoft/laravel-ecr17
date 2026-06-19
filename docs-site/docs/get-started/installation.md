---
title: Installation
description: Package installation and Laravel discovery.
---

# Installation

## Requirements

| Requirement | Value |
| --- | --- |
| PHP | `^8.3` |
| Laravel components | `illuminate/support:^13.0`, `illuminate/contracts:^13.0` |
| Network | TCP reachability from PHP runtime to POS LAN address |

## Composer

```bash
composer require padosoft/laravel-ecr17
```

Laravel package discovery registers `Padosoft\Ecr17\Ecr17ServiceProvider` and the `Ecr17` alias.

## Optional config publishing

```bash
php artisan vendor:publish --tag=ecr17-config
```

::: callout tip "Framework-free core"
The protocol, response, session, and transport layers are plain PHP. Laravel is a thin integration layer around the core client.
:::

## Smoke test

```php
use Padosoft\Ecr17\Facades\Ecr17;

$connected = Ecr17::isConnected();
```

For local tests, prefer `FakeTransport` rather than requiring a live terminal.
