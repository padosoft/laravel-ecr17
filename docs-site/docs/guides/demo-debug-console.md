---
title: Demo Debug Console
description: Local Laravel demo application.
---

# Demo Debug Console

The `demo/` directory contains a small Laravel app with a React and Tailwind debug console loaded from CDNs.

```bash
cd demo
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Open `http://localhost:8000` after the server starts.

::: callout warning "Real terminal"
The demo can send real POS commands. Use a test terminal, controlled amounts, and explicit reconciliation rules.
:::

## What to inspect

::: grids
::: grid
### Connection

Confirm host, port, terminal id, and cash register id before running commands.
:::

::: grid
### Logs

Watch progress messages, receipt lines, and server-side log output.
:::

::: grid
### Commands

Exercise status, totals, payments, last-result recovery, and receipt flows.
:::
:::
