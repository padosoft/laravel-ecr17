<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Protocol;

/**
 * Longitudinal redundancy check used by ECR17: XOR-fold over base 0x7F.
 * Which framing bytes (STX/ETX) are folded in is selected by {@see LrcMode}.
 */
final class Lrc
{
    public const BASE = 0x7F;

    private const STX = 0x02;

    private const ETX = 0x03;

    /**
     * Compute the LRC byte (0-255) for a payload (treated as a byte string).
     */
    public static function compute(string $payload, LrcMode $mode): int
    {
        $lrc = self::BASE;

        if ($mode === LrcMode::Stx || $mode === LrcMode::StxNoext) {
            $lrc ^= self::STX;
        }

        $length = strlen($payload);
        for ($i = 0; $i < $length; $i++) {
            $lrc ^= ord($payload[$i]);
        }

        if ($mode === LrcMode::Stx || $mode === LrcMode::Noext) {
            $lrc ^= self::ETX;
        }

        return $lrc;
    }
}
