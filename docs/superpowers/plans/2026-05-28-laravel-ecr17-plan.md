# laravel-ecr17 — implementation plan

> Execute phase-by-phase; each phase ends with the Definition-of-Done loop
> (AGENTS.md). The RN repo (`react-native-ecr17-protocol`) is the behavioral
> source of truth — port its protocol logic and its tests 1:1.

**Goal:** `padosoft/laravel-ecr17` Composer package (ECR17 over TCP) + a demo
Laravel 13 app with a React/Tailwind/AJAX debug console.

**Tech:** Laravel 13, PHP 8.3+ (Herd php 8.4 locally), Pest + Testbench, Pint,
PHPStan, `stream_socket_client`, Vite + React + Tailwind.

---

## Phase 0 — Scaffolding
- Create the demo Laravel 13 app at repo root (`composer create-project laravel/laravel . "^13.0"` via Herd php, preserving existing LICENSE/README/.git/docs).
- Create `packages/laravel-ecr17/` with `composer.json` (`"php": "^8.3"`, PSR-4
  `Padosoft\\Ecr17\\`), and wire the app's root `composer.json` with a path
  repository → the package; `require padosoft/laravel-ecr17: *`.
- Dev tooling in the package: `pestphp/pest`, `orchestra/testbench`,
  `laravel/pint`, `phpstan/phpstan` (+ larastan). `composer test/lint/analyse` scripts.
- **DoD:** `composer install` works; `php artisan serve` boots; empty Pest run green.

## Phase 1 — Protocol core (pure PHP, TDD, port RN tests first)
For each unit: port the RN gtest cases into Pest FIRST (RED), then implement (GREEN).
- `Protocol/Lrc` (+ test: 4 modes, known vectors from RN `test_lrc`).
- `Protocol/PacketCodec` (encode/decode/control; + RN `test_packet_codec`).
- `Protocol/Ecr17Protocol` builders (+ RN `test_protocol`/`test_protocol_commands`,
  byte-exact incl. payment `P` = 167B, tokenization `U`).
- `Response/Ecr17Response` + DTOs (+ RN `test_response`, incl. the preAuth pos-48
  cardType fix).
- **DoD:** Pest green; Pint/PHPStan clean.

## Phase 2 — Session + transport + retry policy
- `Transport/TransportInterface`, `Transport/FakeTransport` (scripted, mirror RN).
- `Session/SessionConfig`, `Session/RetryPolicy` (+ RN `test_retry_policy` money-safety).
- `Session/Ecr17Session` (ACK/NAK + retransmit×3 + timeouts + progress/receipt +
  early-APPLICATION-before-ACK + `resetForNewTransaction`) (+ RN `test_session`/`test_flows`).
- `Transport/SocketTransport` (`stream_socket_client`, blocking reader, timeouts).
- **DoD:** Pest green (all RN session/retry scenarios reproduced).

## Phase 3 — Laravel integration
- `Ecr17Client` service (configure/connect/disconnect + all commands + callbacks).
- `Ecr17Config`, `config/ecr17.php`, `Ecr17ServiceProvider`, `Ecr17` Facade, `ecr17()` helper.
- `Events/` ProgressReceived / ReceiptLineReceived / ConnectionStateChanged.
- Testbench tests for provider/facade/config publishing.
- **DoD:** Pest + Testbench green.

## Phase 4 — Demo debug console (Blade + React + Tailwind + AJAX)
- Routes/controller: `GET /` (Blade+React island), `POST /ecr17/connect`,
  `POST /ecr17/command/{key}`, `POST /ecr17/disconnect`, `GET /ecr17/logs`.
- Server log buffer (cache/file), PAN masked; React polls `/ecr17/logs`.
- React UI (Vite + Tailwind): config form (persisted), connection bar, command
  palette (money €→cents), live log, busy/progress overlay. Synchronous exec
  (raised timeout) — documented as demo-only.
- **DoD:** `php artisan serve` + `npm run dev`; manual run against a POS/fake.

## Phase 5 — CI + READMEs + cross-ref + finalize
- GitHub Actions: Pest matrix (PHP 8.3/8.4) + Pint + PHPStan.
- "Wow" Laravel-community README (adapt RN positioning) + cross-link to the RN repo
  (RN repo already links here).
- Finalize AGENTS.md/CLAUDE.md/docs/LESSON.md; add PROGRESS.md.
- README production note: queue + AJAX polling or Octane/Swoole (don't block FPM).

## Self-review
All spec sections mapped to a phase; protocol + money-safety covered by ported
tests in Phases 1–2; demo + CI + READMEs + cross-ref in Phases 4–5.
