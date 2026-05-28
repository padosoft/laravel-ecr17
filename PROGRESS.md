# PROGRESS — laravel-ecr17

Resume state for crash-safe continuation. See `docs/superpowers/plans/` for the
full plan and `AGENTS.md` for the workflow.

## Layout decision
Package at **repo root** (`padosoft/laravel-ecr17`: `src/`, `tests/`, `config/`)
+ demo Laravel app in **`demo/`** (cleaner/standard than the original spec's
"package in packages/ + app at root"; avoids `create-project` into a non-empty root).
Local toolchain: **Herd** php 8.4 (`~/.config/herd/bin/php84/php.exe`) + composer.

## Status
- [x] Phase 0 — scaffolding: composer.json (php ^8.3, L13 deps), Pest + Testbench,
      Pint, PHPStan/Larastan, ServiceProvider + Facade + config/ecr17.php stubs.
      Pest green (sanity + config-merge). 
- [x] Phase 1 — protocol core (Lrc/PacketCodec/Ecr17Protocol/Ecr17Response) + tests (ported from RN).
- [x] Phase 2 — Transport (interface + Socket + Fake) + Session + RetryPolicy + tests. 68 green.
- [x] Phase 3 — Ecr17Client (all commands, money-safety, PROACTIVE reconnect via isAlive/MSG_PEEK) + provider binding + facade. 74 green.
- [x] Phase 4 — demo app (Laravel 13 in demo/, React+Tailwind+AJAX console via CDN, controller+routes+DemoLog).
- [x] Phase 5 — CI (Pest matrix + Pint + PHPStan, green) + wow README + cross-ref to RN. PACKAGE COMPLETE.

## Source of truth
RN repo `react-native-ecr17-protocol` (`package/cpp/**` + `package/cpp/tests/**`).
Port byte layouts, offsets, money-safety, and tests from there.
