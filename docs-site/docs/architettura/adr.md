---
title: ADR
description: Architecture decision records.
---

# ADR

::: collapsible open "ADR 001: Framework-free protocol core"
## Context

Laravel users need a facade and service provider, but protocol behavior must be testable without a Laravel application.

## Decision

Keep `Protocol`, `Response`, `Session`, and `Transport` free of Laravel dependencies. Bind the client in the service provider.

## Consequences

Tests can use `FakeTransport`, and other PHP runtimes can reuse the core classes.
:::

::: collapsible "ADR 002: No financial replay after disconnect"
## Context

A TCP disconnect can happen after the terminal charged the card and before PHP received the response.

## Decision

Only safe read-like commands can replay after reconnect. Financial commands surface failure and require `sendLastResult()`.

## Consequences

The package favors reconciliation over hidden duplicate-charge risk.
:::

::: collapsible "ADR 003: Synchronous session"
## Context

PHP applications often execute command-style integrations synchronously, while the sibling React Native core uses asynchronous runtime patterns.

## Decision

Drive `Transport::read()` synchronously inside `Ecr17Session`.

## Consequences

The API is easy to call from jobs and commands, but production HTTP requests should not block on live cardholder interaction.
:::
