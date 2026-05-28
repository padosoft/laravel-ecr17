# laravel-ecr17 — design (PHP/Laravel port of ECR17)

Date: 2026-05-28
Status: approved (brainstorming)
Sibling project: `react-native-ecr17-protocol` (the iOS/Android/RN implementation) —
this PHP port mirrors its protocol core and money-safety model.

## Goal
A reusable **Composer package** `padosoft/laravel-ecr17` implementing the Italian
**ECR17 / "Protocollo 17"** payment protocol (Nexi Group POS terminals) over TCP,
plus a **demo Laravel app** in the same repo with a React debug console.

The package is the product; the demo app is a debug/showcase tool.

## Decisions (brainstorming)
- **Shape:** Composer package + demo app in one repo (monorepo).
- **Execution:** the demo runs commands **synchronously** (raised timeout). The
  README must state it is a debug/demo app; for **production** integrate the
  package with **queue + AJAX polling** or **Octane/Swoole** (a 60s blocking
  exchange must not tie up PHP-FPM workers).
- **Frontend:** Blade + React (Vite) + Tailwind + **AJAX** (JSON endpoints).
- **Realtime:** AJAX **polling** of a logs endpoint.
- **Stack:** **Laravel 13** → **PHP 8.3+** (authoritative: Laravel 13 requires PHP
  8.3, range 8.3–8.5). `"php": "^8.3"`, `"laravel/framework": "^13.0"`.
  Local toolchain: **Laravel Herd** (`C:\Users\lopad\.config\herd\bin\`) provides
  PHP 8.2/8.4/8.5 + composer; default `php` = **8.4.21** → use it for the demo
  (XAMPP's bundled PHP 8.2 is too old for L13).
- **Socket:** `stream_socket_client` (timeouts, blocking reader). The Laravel
  server must reach the POS over TCP on the LAN.

## Repo layout (monorepo: package + demo)
```
laravel-ecr17/
├─ packages/laravel-ecr17/            # the Composer package (padosoft/laravel-ecr17)
│  ├─ src/
│  │  ├─ Protocol/ Lrc.php PacketCodec.php Ecr17Protocol.php
│  │  ├─ Response/ Ecr17Response.php + DTOs (PaymentResult, PreAuthResult, …)
│  │  ├─ Session/  Ecr17Session.php RetryPolicy.php SessionConfig.php
│  │  ├─ Transport/ TransportInterface.php SocketTransport.php FakeTransport.php
│  │  ├─ Ecr17Client.php Ecr17Config.php
│  │  ├─ Events/   ProgressReceived.php ReceiptLineReceived.php ConnectionStateChanged.php
│  │  ├─ Facades/  Ecr17.php
│  │  └─ Ecr17ServiceProvider.php
│  ├─ config/ecr17.php
│  ├─ tests/ (Pest + scripted scenarios mirrored from the RN gtest suite)
│  └─ composer.json   (php ^8.3)
├─ app/ routes/ resources/ …          # demo Laravel 13 app (repo root)
├─ composer.json                      # app; path repository -> packages/laravel-ecr17
├─ AGENTS.md CLAUDE.md docs/LESSON.md # vibe-coding kit (seeded from RN, cross-language)
└─ README.md                          # "wow" README for the Laravel community
```
The demo app declares `repositories: [{type: path, url: packages/laravel-ecr17}]`
and requires `padosoft/laravel-ecr17: *`, so `composer install && php artisan serve`
works out of the box.

## Package — protocol core (1:1 port of the RN C++ core; namespace `Padosoft\Ecr17`)
The RN C++ implementation is the SOURCE OF TRUTH for behavior. Port:
- **`Lrc`** — XOR fold over base `0x7F`; 4 modes `stx|std|noext|stx_noext` (which
  framing bytes are folded).
- **`PacketCodec`** — bytes: STX `0x02`, ETX `0x03`, SOH `0x01`, EOT `0x04`,
  ACK `0x06`, NAK `0x15`. `encodeApplication(payload)` = STX+payload+ETX+LRC;
  `encodeControl(byte)`; `decode(frame)` treats the buffer as exactly one frame
  (LRC = last byte) and returns `{type, payload, validLrc}`; progress = SOH+20+EOT
  (no LRC). Frame extraction (stream→frames) lives in the transport/session.
- **`Ecr17Protocol`** — builders: status `s`, pay `P` (167B), payExtended `X`,
  reverse `S`, preAuth `p`, incrementalAuth `i`, preAuthClosure `c`, verifyCard `H`,
  closeSession `C`, totals `T`, sendLastResult `G`, enableEcrPrinting `E`,
  reprint `R`, vas `K`; tokenization additional-data message `U`.
- **`Ecr17Response`** — typed parsers → readonly DTOs (PaymentResult,
  PreAuthResult, StatusResponse, TotalsResult, CloseResult, CardVerificationResult,
  ReversalResult, VasResult). Outcome mapping 00→ok / 01→ko / 05→cardNotPresent /
  09→unknownTag. **Port the preAuth offset fix** (pos 48 is an amount digit on the
  OK layout, not cardType → only read cardType for KO).
- **`Ecr17Session`** — ACK/NAK handshake + retransmit up to 3 + ack/response
  timeouts; forward progress (SOH) + receipt (`S`) frames; ACK valid frames / NAK
  bad LRC; **handle an APPLICATION frame that arrives before/without an ACK**
  (stash → return → consume in waitForResult); `resetForNewTransaction()` for reuse
  across reconnects.
- **`RetryPolicy`** — money-safety: a financial command is NEVER blindly retried
  after a drop; only read-only/idempotent ops may be replayed; recover a lost
  result via `sendLastResult` (`G`). Mirror `RetryPolicy.hpp` + its unit tests.
- **`TransportInterface`** + **`SocketTransport`** (`stream_socket_client`,
  connect/send/read-loop/close, timeouts) + **`FakeTransport`** (deterministic,
  scripted replies — the test double; mirror the RN `FakeTransport` semantics:
  each application send pops the next scripted reply; supports disconnect-on-send).
- **`Ecr17Client`** — high-level service: `configure/connect/disconnect`,
  all commands (each a full exchange), event callbacks; uses Session+Transport.

## Laravel integration
- **`Ecr17ServiceProvider`** — publishes `config/ecr17.php`, binds `Ecr17Client`
  (singleton, built from config).
- **`Ecr17` Facade** + `ecr17()` helper.
- **Events:** `ProgressReceived`, `ReceiptLineReceived`, `ConnectionStateChanged`.
- **Config** (`config/ecr17.php`): host, port (1024), terminalId, cashRegisterId,
  lrcMode, connection/response/ack timeouts, retryCount/Delay, receiptDrainMs, debug.

## Demo app — debug console (Blade + React + Tailwind + AJAX)
- **`GET /`** → Blade view mounting a React (Vite) island; Tailwind dark "console".
- JSON endpoints (controller): `POST /ecr17/connect`, `POST /ecr17/command/{key}`,
  `POST /ecr17/disconnect`, `GET /ecr17/logs` (polling).
- A server-side **log buffer** (cache/file) with levels (sent/recv/progress/
  receipt/ok/ko/error), **PAN masked**; React polls `/ecr17/logs`.
- UI ≈ the RN Debug Console: config form (persisted), connection bar, command
  palette (all commands; money fields €→cents), live log, busy/progress overlay.
- Synchronous execution with a raised timeout (documented as demo-only).

## Testing (a first-class requirement)
- **Pest** unit tests for the package, run against **real scripted scenarios**
  via `FakeTransport` — port the RN gtest cases 1:1 (LRC, PacketCodec framing,
  protocol builders byte-exact, response parsers at exact offsets, session
  ACK/NAK/retransmit/timeout/progress/receipt/early-APPLICATION, retry-policy
  money-safety). The low-level protocol + services MUST be proven against these.
- **Orchestra Testbench** for ServiceProvider/Facade/config publishing.
- Optional opt-in integration test against a real POS (env-gated), like the RN
  `test_integration_terminal`.
- CI: GitHub Actions matrix (PHP 8.3/8.4) running Pest; Pint (lint) + PHPStan.

## READMEs + cross-reference
- **Laravel "wow" README** (community-facing): adapt the RN README's positioning
  for the Laravel/PHP audience (install via composer, facade/helper usage, config,
  events, the demo console, production guidance: queue/Octane).
- **Cross-reference (both repos):**
  - Laravel README: "Looking for iOS/Android (React Native)? → react-native-ecr17-protocol".
  - RN README: "Looking for a PHP/Laravel port? → laravel-ecr17".

## Vibe-coding kit (seeded from RN, cross-language)
Create `AGENTS.md`, `CLAUDE.md`, `docs/LESSON.md` carrying over the cross-language
learnings from the RN repo: the ECR17 protocol facts, the money-safety
non-negotiable, the per-phase Definition-of-Done loop (local tests + review →
CI green), test-against-real-scenarios discipline, and PHP/Laravel-specific
conventions. Start work with these in context.

## Money-safety (non-negotiable, identical to RN)
Never blindly re-send payments/reversals/pre-auths after a drop; reconnect and
retry ONLY read-only/idempotent ops; recover a lost result via `sendLastResult`
(`G`). Locked by `RetryPolicy` + its tests.

## Out of scope (for the first cut)
- Production queue/Octane wiring (documented, not implemented in the demo).
- WebSocket realtime (polling only).
