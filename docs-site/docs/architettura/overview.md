---
title: Architecture Overview
description: Internal package architecture.
---

# Architecture Overview

The architecture keeps Laravel integration thin and isolates protocol behavior in framework-free PHP classes.

```mermaid
flowchart TB
    subgraph Laravel
        Provider[Ecr17ServiceProvider]
        Facade[Ecr17 facade]
        ConfigFile[config/ecr17.php]
    end
    subgraph Core
        Client[Ecr17Client]
        Session[Ecr17Session]
        Retry[RetryPolicy]
        Protocol[Ecr17Protocol]
        Codec[PacketCodec and Lrc]
        Responses[Ecr17Response parsers]
    end
    subgraph Transport
        Socket[SocketTransport]
        Fake[FakeTransport]
    end
    Provider --> Client
    Facade --> Client
    ConfigFile --> Client
    Client --> Session
    Client --> Protocol
    Client --> Responses
    Client --> Retry
    Session --> Codec
    Session --> Transport
```

::: callout info "Design rule"
`Ecr17Client` decides command semantics; `Ecr17Session` drives one exchange; `PacketCodec` handles bytes; response classes expose domain fields.
:::

## Limits

The package does not provide persistence, order state machines, or a hosted terminal simulator. Applications must own those boundaries.
