# LESSON.md — accumulated learnings (laravel-ecr17)

> **Context rule:** read this at the start of every session and keep it updated —
> especially after CI/review feedback and after fixing any bug. Pass its content
> into any sub-agent prompt you spawn.

This project is the PHP/Laravel port of `react-native-ecr17-protocol`. The lessons
below are **seeded from the RN repo** (cross-language) so we start strong; add
PHP/Laravel-specific learnings as we build.

## Environment & tooling
- Host is **Windows**; the `Bash` tool runs **bash**, `PowerShell` runs pwsh.
  Use bash heredocs / `git commit -F -`; never PowerShell here-strings in Bash.
- **PHP/Composer via Laravel Herd** at `C:\Users\lopad\.config\herd\bin\`:
  `php.bat` (default **PHP 8.4.21**, NTS), plus `php82/`, `php84/`, `php85/`
  (`.../php84/php.exe`), and `composer.bat`. Laravel 13 needs **PHP 8.3+** →
  use 8.4/8.5 (XAMPP's bundled 8.2 is too old). Run Pest/artisan/composer locally
  with Herd's php — a real local RED→GREEN loop is available (unlike the RN repo,
  where native couldn't be built locally).
- Laravel 13 released **2026-03-17**, requires **PHP 8.3–8.5** (authoritative:
  laravel.com/docs/13.x/releases). Use `"php": "^8.3"`, `"laravel/framework": "^13.0"`.

## Protocol facts (cross-language — RN is the source of truth)
- LRC = base `0x7F` XOR-folded; `LrcMode` (`stx|std|noext|stx_noext`) selects which
  framing bytes are folded.
- Bytes: STX `0x02`, ETX `0x03`, SOH `0x01`, EOT `0x04`, ACK `0x06`, NAK `0x15`.
- App frame = STX+payload+ETX+LRC; `decode()` = exactly one frame (LRC = last byte).
  Progress = SOH + 20-char msg + EOT, **no LRC**.
- Status code lowercase `'s'`; payment `'P'` = 167 bytes; receipts = `S` messages;
  reversal command = `S`.
- Commands: status `s`, pay `P`, payExtended `X`, reverse `S`, preAuth `p`,
  incrementalAuth `i`, preAuthClosure `c`, verifyCard `H`, closeSession `C`,
  totals `T`, sendLastResult `G`, enableEcrPrinting `E`, reprint `R`, vas `K`,
  tokenization additional-data `U`.
- Outcome map: 00→ok, 01→ko, 05→cardNotPresent, 09→unknownTag.
- **preAuth OK layout**: amount occupies positions 41-48, so pos 48 is an amount
  digit, NOT cardType — read cardType only for the KO layout (RN regression).

## Money-safety (non-negotiable)
- Never blindly re-send a financial command (pay/reverse/pre-auth) after a drop —
  double-charge risk. Reconnect, then retry ONLY read-only/idempotent ops; recover
  a lost result via `sendLastResult` (`G`). Lock it in `RetryPolicy` + tests.
- The session must reset its connection state per transaction
  (`resetForNewTransaction`) so it's reusable across reconnects — RN bug: a sticky
  "disconnected" flag broke auto-reconnect until reset per exchange.
- Mask PAN in any log (screen + file/export); keep last 4 only.
- **Proactive reconnection before sending.** ECR17/Nexi terminals often close the
  TCP socket between transactions, and a half-open socket isn't detectable without
  a read (`feof()`/`isConnected()` report stale `true`). If a financial command is
  sent on that stale socket it fails reactively → money-safety (correctly) refuses
  to retry → false error; the side-effect reconnect then makes the NEXT attempt
  work ("one yes, one no"). Fix: `Ecr17Client::ensureConnected()` does a
  NON-DESTRUCTIVE liveness probe (`TransportInterface::isAlive()` → `stream_select`
  + `stream_socket_recvfrom(..., STREAM_PEEK)` in `SocketTransport`) and reconnects
  BEFORE sending, so every command starts on a verified-live socket. (Same fix
  applied on the RN side.) Money-safety unchanged: a genuine MID-exchange drop is
  still never retried for financial commands.

## Session orchestration gotchas (from RN)
- Handle an **APPLICATION result that arrives before/without the ACK** during the
  handshake — stash it and let `waitForResult` consume it, else a completed
  financial transaction is lost to a handshake timeout.
- On a failed connect, emit a DISCONNECTED state — don't leave listeners stuck on
  CONNECTING.

## Testing discipline (from RN — apply here with Pest)
- Prove the low-level protocol + services against **real scripted scenarios** via
  a deterministic `FakeTransport` (pops the next scripted reply per application
  send; supports disconnect-on-send). Port the RN gtest cases 1:1.
- FakeTransport makes happy-path tests synchronous; only timeout tests wait → use
  tiny timeouts.
- Keep the protocol core **framework-free** (pure PHP) so it's trivially testable;
  Laravel glue is a thin layer tested via Orchestra Testbench.

## Process (from RN — Definition of Done)
- Dual loop: **local** (tests + AI review) THEN **remote** (CI green + remote PR
  review until zero actionable comments) before a task is done.
- **Verify AI-review suggestions** against the spec/RN behavior — don't apply
  blindly (some RN Copilot/Codex suggestions were wrong; some were valuable).
- A demo/example app reveals **real bugs only at runtime** — run it; CI compiling
  is not the same as it working.

## Cross-reference
- Sibling repo (iOS/Android, React Native + Nitro/C++): `react-native-ecr17-protocol`.
  It's the behavioral source of truth for the protocol. Each repo's README links
  the other.
