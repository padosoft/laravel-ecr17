# ECR17 Debug Console (demo app)

A small Laravel app that consumes **`padosoft/laravel-ecr17`** (linked from `../`
via a Composer path repository) and exposes a **React + Tailwind + AJAX** debug
console: configure & connect to a POS terminal, run every ECR17 command, and watch
the behind-the-scenes log (sent / progress / receipt / result / error) live.

> ⚠️ **Debug/demo only.** Commands run **synchronously** — a payment blocks until
> the cardholder finishes (up to `response_timeout_ms`, ~60s). That is fine for a
> single-operator debug tool but would tie up a PHP-FPM worker in production. For
> production, drive the package from a **queued job** (Laravel Queue + worker) with
> AJAX polling, or run it under **Octane/Swoole**. See the package README.

## Run

Requires **PHP 8.3+** (e.g. Laravel Herd) and the POS terminal reachable over TCP.

```bash
cd demo
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Open <http://localhost:8000>. Enter the terminal **host/port/terminalId/cashRegisterId**
in the Configuration panel (persisted in your browser), then tap a command. Logs
poll automatically.

There's no `npm`/Vite build: the frontend loads React + Tailwind from a CDN and
talks to JSON endpoints (`/ecr17/command/{key}`, `/ecr17/logs`) via `fetch`.

## How it works

- `routes/web.php` → `App\Http\Controllers\Ecr17DemoController`.
- The controller builds a `Padosoft\Ecr17\Ecr17Client` from the config posted by
  the UI (so you can point it at any terminal without editing `.env`), wires
  progress/receipt/connection callbacks to a small cache-backed `App\Support\DemoLog`
  (PAN masked), runs the command, and returns the parsed result as JSON.
- Each HTTP request opens its own socket — which mirrors how ECR17 terminals
  typically close the TCP connection after each transaction. Money-safety and the
  proactive reconnection live in the package.

## Notes

- **CDN scripts**: for convenience the demo loads React/Tailwind/Babel from CDNs
  without Subresource Integrity. It's a local dev tool; if you expose it anywhere,
  pin + self-host the assets (or add `integrity`/`crossorigin`) and compile with
  Vite instead of the in-browser Babel transform.
