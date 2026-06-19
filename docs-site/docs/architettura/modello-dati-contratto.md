---
title: Modello Dati Contratto
description: Data model and API contract.
---

# Modello Dati Contratto

## Contract layers

| Layer | Contract |
| --- | --- |
| `Ecr17Config` | Immutable runtime values for host, port, identifiers, timeouts, retry settings, LRC mode, and reconnect behavior. |
| `TransportInterface` | `connect`, `disconnect`, `isConnected`, `isAlive`, `send`, and `read`. |
| `Ecr17Session` | One synchronous exchange at a time over a transport. |
| `DecodedPacket` | Packet type, payload, and LRC validity. |
| Response DTOs | Parsed fields for payment, status, totals, close, pre-auth, DCC, and VAS results. |

## Amount model

Amounts are passed as integer cents. For an amount `e` in euros:

$$
cents = round(e \times 100)
$$

Avoid floats in persistence. Store cents as integers.

## Worked example

```php
$config = new Ecr17Config(
    host: '192.168.1.50',
    port: 1024,
    terminalId: '12345678',
    cashRegisterId: '1',
    responseTimeoutMs: 90000,
);
```

::: callout warning "Limits"
The protocol parser can parse terminal payloads, but it cannot validate that a payload belongs to the order you intended. Persist and compare business correlation fields.
:::
