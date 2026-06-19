---
title: Configuration API
description: Ecr17Config constructor and session mapping.
---

# Configuration API

`Ecr17Config` is the runtime configuration object passed to `Ecr17Client`.

```php
new Ecr17Config(
    host: '192.168.1.50',
    port: 1024,
    terminalId: '12345678',
    cashRegisterId: '1',
    lrcMode: 'std',
    autoReconnect: true,
    connectionTimeoutMs: 5000,
    responseTimeoutMs: 60000,
    ackTimeoutMs: 2000,
    retryCount: 3,
    retryDelayMs: 200,
    receiptDrainMs: 0,
);
```

## Session mapping

`toSessionConfig()` passes LRC mode, timeouts, retry settings, and receipt drain behavior into `Ecr17Session`.

::: callout tip "Multiple terminals"
Instantiate one config/client pair per terminal when a single Laravel app controls multiple POS devices.
:::
