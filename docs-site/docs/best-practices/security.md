---
title: Security
description: Security and data-handling guidance.
---

# Security

## Network boundary

POS terminals should live on a controlled LAN segment. The PHP runtime only needs TCP access to configured terminals.

## Data handling

::: callout warning "Payment data"
Do not log full PANs, track data, or sensitive terminal payloads. Keep logs useful for reconciliation without becoming cardholder-data storage.
:::

## Configuration hygiene

| Item | Recommendation |
| --- | --- |
| Terminal IP | Store in environment or secret-backed configuration. |
| Debug logs | Disable in production unless actively diagnosing. |
| Receipt text | Avoid secrets and personal data. |
| Tokenization contract | Treat as durable payment metadata. |
