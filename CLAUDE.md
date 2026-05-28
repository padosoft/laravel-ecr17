# CLAUDE.md

This repo is the **PHP/Laravel port** of the ECR17 POS protocol and ships
first-class context for AI coding agents. Start here:

- **[AGENTS.md](AGENTS.md)** — project guide, stack (Laravel 13 / PHP 8.3+), the
  mandatory per-phase Definition-of-Done loop, testing strategy. **Read it first.**
- **[docs/LESSON.md](docs/LESSON.md)** — accumulated, cross-language engineering
  lessons (protocol facts, money-safety, session gotchas, toolchain). Re-read at
  the start of every session; pass into every sub-agent prompt.
- **PROGRESS.md** — current task / resume state (created during implementation).

## Non-negotiables
- 💰 **Money-critical:** a financial command is **never blindly re-sent** after a
  reconnect (double-charge). Decision lives in `RetryPolicy` (package), locked by
  its tests; recover a lost response via `sendLastResult()` (command `G`). Mask
  PAN in all logs.
- **Behavioral source of truth:** the sibling repo `react-native-ecr17-protocol`
  (iOS/Android, React Native + Nitro/C++). Match its byte layouts, offsets, and
  money-safety rules; port its test suite into Pest.
- **Keep CI green** (Pest matrix PHP 8.3/8.4 + Pint + PHPStan) and follow the
  dual loop in AGENTS.md (local tests+review → remote CI+review) before merging.
- **Local toolchain:** Laravel **Herd** (`~/.config/herd/bin`, default PHP 8.4) —
  XAMPP's PHP 8.2 is too old for Laravel 13.
- The protocol core stays **framework-free** (pure PHP); Laravel is a thin wrapper.
