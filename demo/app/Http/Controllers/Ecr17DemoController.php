<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DemoLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\Ecr17\Ecr17Client;
use Padosoft\Ecr17\Ecr17Config;
use Padosoft\Ecr17\Transport\SocketTransport;
use Throwable;

class Ecr17DemoController extends Controller
{
    public function index(): \Illuminate\Contracts\View\View
    {
        return view('ecr17');
    }

    public function logs(): JsonResponse
    {
        return response()->json(['entries' => DemoLog::all()]);
    }

    public function clearLogs(): JsonResponse
    {
        DemoLog::clear();

        return response()->json(['ok' => true]);
    }

    public function connect(Request $request): JsonResponse
    {
        try {
            $this->client($request)->connect();

            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            DemoLog::add('error', 'connect failed', $e->getMessage());

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
        }
    }

    public function command(Request $request, string $key): JsonResponse
    {
        /** @var array<string,mixed> $params */
        $params = (array) $request->input('params', []);
        $client = $this->client($request);

        DemoLog::add('sent', $key, $params);

        try {
            $result = $this->dispatch($client, $key, $params);
            $isOk = $this->isOk($result);
            DemoLog::add($isOk ? 'ok' : 'ko', "{$key} →", $this->toArray($result));

            return response()->json(['ok' => true, 'result' => $result]);
        } catch (Throwable $e) {
            DemoLog::add('error', "{$key} failed", $e->getMessage());

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
        }
    }

    private function client(Request $request): Ecr17Client
    {
        /** @var array<string,mixed> $cfg */
        $cfg = (array) $request->input('config', []);
        $config = Ecr17Config::fromArray($cfg);
        $transport = new SocketTransport($config->host, $config->port, $config->connectionTimeoutMs);
        $client = new Ecr17Client($transport, $config);

        $client->setOnProgress(fn (string $m) => DemoLog::add('progress', $m));
        $client->setOnReceiptLine(fn (string $l) => DemoLog::add('receipt', $l));
        $client->setOnConnectionStateChange(fn (string $s) => DemoLog::add('info', "connection: {$s}"));

        return $client;
    }

    private function dispatch(Ecr17Client $client, string $key, array $p): mixed
    {
        $str = fn (string $k): string => is_string($p[$k] ?? null) ? $p[$k] : '';
        $int = fn (string $k): int => (int) ($p[$k] ?? 0);
        $bool = fn (string $k): bool => (bool) ($p[$k] ?? false);
        $type = $str('paymentType') !== '' ? $str('paymentType') : 'auto';

        return match ($key) {
            'status' => $client->status(),
            'pay' => $client->pay($int('amountCents'), $type, $bool('cardAlreadyPresent'), $this->reg($p), $str('receiptText')),
            'payExtended' => $client->payExtended($int('amountCents'), $type, $bool('cardAlreadyPresent'), $this->reg($p), $str('receiptText')),
            'reverse' => $client->reverse($str('stan') !== '' ? $str('stan') : null, $this->reg($p)),
            'preAuth' => $client->preAuth($int('amountCents'), $type, $bool('cardAlreadyPresent'), $this->reg($p), $str('receiptText')),
            'incrementalAuth' => $client->incrementalAuth($int('amountCents'), $str('originalPreAuthCode'), $this->reg($p), $str('receiptText')),
            'preAuthClosure' => $client->preAuthClosure($int('amountCents'), $str('originalPreAuthCode'), $this->reg($p), $str('receiptText')),
            'verifyCard' => $client->verifyCard($type, $this->reg($p)),
            'closeSession' => $client->closeSession($this->reg($p)),
            'totals' => $client->totals($this->reg($p)),
            'sendLastResult' => $client->sendLastResult($this->reg($p)),
            'enableEcrPrinting' => $client->enableEcrPrinting($bool('enabled')),
            'reprint' => $client->reprint($bool('toEcr')),
            'vas' => $client->vas($str('xmlRequest')),
            default => throw new \InvalidArgumentException("Unknown command: {$key}"),
        };
    }

    /** @param array<string,mixed> $p */
    private function reg(array $p): ?string
    {
        return (isset($p['cashRegisterId']) && is_string($p['cashRegisterId']) && $p['cashRegisterId'] !== '')
            ? $p['cashRegisterId']
            : null;
    }

    private function isOk(mixed $result): bool
    {
        if ($result === null) {
            return true; // void command (enable/reprint)
        }
        if (isset($result->responseId)) {
            return $result->responseId === '0'; // VAS
        }
        if (isset($result->outcome)) {
            return $result->outcome->value === 'ok';
        }

        return true; // status / informational
    }

    private function toArray(mixed $result): array|string
    {
        if ($result === null) {
            return 'ok';
        }

        return json_decode((string) json_encode($result), true) ?? [];
    }
}
