---
title: Motivazione
description: Why laravel-ecr17 exists.
---

# Motivazione

ECR17 integrations are deceptively small: a cash register sends an amount to a POS, the POS returns a result. The hard part is preserving financial correctness when TCP, terminals, and PHP request lifecycles behave imperfectly.

::: callout info "One-line motivation"
`laravel-ecr17` makes the ECR17 terminal exchange explicit, testable, and money-safe for PHP and Laravel teams.
:::

## Design pressure

| Pressure | Consequence |
| --- | --- |
| POS terminals close sockets between transactions | The client probes liveness and reconnects before each command. |
| Physical ACK and application result are different layers | Session code models both phases separately. |
| A payment result can be lost after the card is charged | Financial commands are not blindly retried. |
| Receipts and progress are streamed messages | Callbacks expose progress and receipt lines during the exchange. |

## Worked example

A queue job starts `pay(1000)`. The terminal charges the card but the network drops before PHP reads the application response. The correct action is not another `pay(1000)`; it is `sendLastResult()` and order reconciliation.

::: callout warning "Limits"
The package cannot decide your business reconciliation policy. It gives you the protocol result and recovery command; your application must map that result to one order exactly once.
:::
