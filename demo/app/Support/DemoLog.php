<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Tiny server-side ring buffer for the debug console (polled via AJAX). Stored in
 * the cache so it survives across the per-request lifecycle. PAN is masked.
 */
final class DemoLog
{
    private const KEY = 'ecr17.demo.log';

    private const MAX = 500;

    /** @param array<string,mixed>|string|null $detail */
    public static function add(string $level, string $label, array|string|null $detail = null): void
    {
        $entries = self::all();
        $entries[] = [
            'id' => uniqid('', true),
            'ts' => (int) (microtime(true) * 1000),
            'level' => $level,
            'label' => $label,
            'detail' => is_array($detail) ? self::maskJson($detail) : ($detail ?? ''),
        ];
        if (count($entries) > self::MAX) {
            $entries = array_slice($entries, -self::MAX);
        }
        Cache::forever(self::KEY, $entries);
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return Cache::get(self::KEY, []);
    }

    public static function clear(): void
    {
        Cache::forget(self::KEY);
    }

    /** @param array<string,mixed> $data */
    private static function maskJson(array $data): string
    {
        return (string) json_encode(self::maskSensitive($data));
    }

    private static function maskSensitive(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = ($k === 'pan' && is_string($v)) ? self::maskPan($v) : self::maskSensitive($v);
            }

            return $out;
        }

        return $value;
    }

    private static function maskPan(string $pan): string
    {
        $digits = preg_replace('/\D/', '', $pan) ?? '';
        if (strlen($digits) <= 4) {
            return '****';
        }

        return str_repeat('*', strlen($digits) - 4).substr($digits, -4);
    }
}
