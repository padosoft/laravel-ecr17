---
title: Testing
description: Testing strategy for ECR17 applications.
---

# Testing

Use package tests as examples: protocol, LRC, session retry, client behavior, service provider registration, and response parsing are covered separately.

::: grids
::: grid
### Unit

Use `FakeTransport` and verify exact command behavior.
:::

::: grid
### Integration

Use a terminal simulator or controlled POS lab before connecting stores.
:::

::: grid
### Acceptance

Verify operational recovery: drop connection, call `sendLastResult()`, and reconcile.
:::
:::

## Commands

```bash
composer test
composer lint
composer analyse
```

::: callout tip "Golden frames"
Keep known ECR17 byte frames as fixtures for parser and LRC regression tests.
:::
