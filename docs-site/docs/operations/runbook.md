---
title: Runbook
description: Operational runbook for incidents.
---

# Runbook

::: steps
1. Identify the affected terminal id, POS IP, order id, and command.
2. Check whether the failure happened before ACK, after ACK, or while waiting for result.
3. For financial uncertainty, run `sendLastResult()` once from a controlled operator action or recovery job.
4. Compare amount, STAN, auth code, and outcome against the order.
5. Store the resolved state and prevent duplicate payment attempts.
6. Escalate terminal/network faults with timestamps and NAK/timeout counts.
:::

::: callout warning "Operator UI"
Expose recovery as a deliberate action with audit logging. Avoid buttons that simply re-run the original payment.
:::
