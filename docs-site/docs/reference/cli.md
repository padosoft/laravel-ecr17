---
title: CLI
description: CLI and maintenance commands.
---

# CLI

The package itself exposes Laravel integration rather than a dedicated package CLI. Use project commands for install, test, and demo workflows.

| Task | Command |
| --- | --- |
| Install package | `composer require padosoft/laravel-ecr17` |
| Publish config | `php artisan vendor:publish --tag=ecr17-config` |
| Run package tests | `composer test` |
| Run Pint check | `composer lint` |
| Run PHPStan | `composer analyse` |
| Start demo app | `cd demo && php artisan serve` |

::: callout info "API first"
Runtime terminal operations are exposed through PHP methods, so they can run from queue jobs, console commands, controllers, or dedicated services.
:::
