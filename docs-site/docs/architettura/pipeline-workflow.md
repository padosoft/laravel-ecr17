---
title: Pipeline Workflow
description: End-to-end request and response workflow.
---

# Pipeline Workflow

## Design

```mermaid
sequenceDiagram
    participant App
    participant Client as Ecr17Client
    participant Session as Ecr17Session
    participant Codec as PacketCodec
    participant POS
    App->>Client: command method
    Client->>Client: ensureConnected()
    Client->>Session: exchange(payload)
    Session->>Codec: encodeApplication(payload)
    Codec-->>Session: STX payload ETX LRC
    Session->>POS: send frame
    POS-->>Session: ACK
    POS-->>Session: SOH progress
    POS-->>Session: STX response ETX LRC
    Session->>POS: ACK
    Session-->>Client: DecodedPacket
    Client-->>App: typed response
```

## Workflow notes

::: steps
1. Build the application payload with `Ecr17Protocol`.
2. Encode it as a physical frame with `PacketCodec`.
3. Complete the ACK/NAK handshake.
4. Stream progress and receipt callbacks.
5. Validate the final application frame LRC.
6. Parse the payload into a typed response object.
:::

::: callout warning "Gotcha"
An application result may arrive before a physical ACK. The session stores that pending result and returns it through the normal result path instead of dropping it.
:::
