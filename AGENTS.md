# AGENTS.md — laravel-ecr17

Guidance for AI agents (and humans) working in this repo. Read this first, then
`docs/LESSON.md` (accumulated, cross-language engineering lessons) and
`PROGRESS.md` (current task/resume state) when present.

## What this is
A **Composer package** `padosoft/laravel-ecr17` implementing the Italian **ECR17 /
"Protocollo 17"** payment protocol (Nexi Group POS terminals) over TCP, plus a
**demo Laravel app** (debug console: Blade + React + Tailwind + AJAX).

**Sibling project / source of truth:** `react-native-ecr17-protocol` is the
iOS/Android (React Native + Nitro/C++) implementation. Its protocol core
(`Lcr`/`PacketCodec`/`Ecr17Protocol`/`Ecr17Response`/`Ecr17Session`/`RetryPolicy`)
and its test suite are the **behavioral source of truth** — this PHP port mirrors
them. When in doubt about a byte layout, offset, or money-safety rule, match RN.

## Stack
- **Laravel 13** → **PHP 8.3+** (authoritative: Laravel 13 requires PHP 8.3,
  range 8.3–8.5). `"php": "^8.3"`, `"laravel/framework": "^13.0"`.
- Package logic is **plain PHP** (no framework coupling in the protocol core);
  Laravel only wraps it (ServiceProvider, Facade, config, events).
- Transport: `stream_socket_client` (the server must reach the POS over LAN TCP).
- Tests: **Pest** + **Orchestra Testbench**; lint **Pint**; static analysis **PHPStan**.
- Demo frontend: Blade + React (Vite) + Tailwind + AJAX (polling).

## Mandatory workflow (Definition of Done)
A task/phase is done ONLY after BOTH loops pass (carried over from the RN repo —
it kept that project always-green and reviewable).

### Local loop (per phase, before pushing)
1. **Local tests green** — `composer test` (Pest), `composer lint` (Pint --test),
   `composer analyse` (PHPStan). The low-level protocol + services MUST be proven
   against real scripted scenarios (see Testing).
2. **Local AI review** of the diff (focused prompt; verify suggestions, don't
   trust blindly). Record takeaways in `docs/LESSON.md`.
3. Zero actionable comments → continue; else fix and go to 1.

### Remote loop (REQUIRED before a task/PR is considered done)
4. **Push**, then **CI green** (Pest matrix PHP 8.3/8.4 + Pint + PHPStan).
5. **Remote PR review** by the configured bots; WAIT for it; re-request after each
   push.
6. **Fix every valid comment** (validate each; reject only with a reason), push,
   re-request. Repeat 4–6 until reviewers report ZERO actionable comments.
7. Only then merge. Update `PROGRESS.md`.

## Testing (first-class)
- Port the RN gtest cases 1:1 into **Pest**, driven by a deterministic
  `FakeTransport` (scripted replies, disconnect-on-send): LRC; PacketCodec
  framing; protocol builders (byte-exact); response parsers (exact 1-based
  offsets); session ACK/NAK + retransmit×3 + ack/response timeouts + progress
  (SOH) / receipt (`S`) forwarding + early-APPLICATION-before-ACK; RetryPolicy
  money-safety. If RN has a test for it, this repo must too.
- `FakeTransport` makes happy-path tests synchronous (it pops the next scripted
  reply on each application send); only timeout tests wait — use tiny timeouts.

## Hard-won rules (cross-language; see docs/LESSON.md)
- 💰 **Money-critical — never blindly retry a financial command.** This terminal
  charges real cards. On a drop, reconnect but do NOT re-send
  payments/reversals/pre-auths (double-charge); recover via `sendLastResult()`
  (command `G`). Decision lives in `RetryPolicy`, locked by its tests. The session
  resets its connection state per transaction so it's reusable across reconnects.
- The **demo app blocks** up to ~60s per payment (cardholder wait). That's fine
  for the demo (sync, raised timeout) but the README MUST tell production users to
  integrate the package with **queue + AJAX polling** or **Octane/Swoole** so a
  blocking exchange never ties up a PHP-FPM worker.
- **Never log full PAN / cardholder data** — mask the PAN in the debug log buffer
  and any exported logs (keep last 4).
- Keep the protocol core **framework-free** (pure PHP) so it stays unit-testable
  and reusable; Laravel glue is a thin layer.

## ECR17 protocol facts (cross-language — match the RN repo)
- LRC = base `0x7F` XOR-folded; which framing bytes are folded is selected by
  `LrcMode` (`stx|std|noext|stx_noext`).
- Frame bytes: STX `0x02`, ETX `0x03`, SOH `0x01`, EOT `0x04`, ACK `0x06`, NAK `0x15`.
- App frame = STX + payload + ETX + LRC. `decode()` treats the buffer as exactly
  one frame (LRC = final byte). Progress = SOH + 20-char msg + EOT (**no LRC**).
- Status command code is lowercase `'s'`; payment `'P'` = 167 bytes.
- Receipts arrive as one or more `S` messages (concatenate). Reversal command = `S`.
- preAuth OK layout: amount occupies positions 41-48, so pos 48 is an amount
  digit, NOT cardType — only read cardType for the KO layout.

## Conventions
- PSR-4 namespace `Padosoft\Ecr17\` for the package; PSR-12 (Pint).
- Commit messages: conventional style; end with the Co-Authored-By trailer.
  Branch + PR per feature; keep CI green per push.
- Cross-reference the sibling RN repo in the README (and vice-versa).
