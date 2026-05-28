<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Protocol;

/**
 * LRC scope selector — which framing bytes are folded into the LRC (base 0x7F):
 *  - Stx:      STX + payload + ETX
 *  - Std:      payload only
 *  - Noext:    payload + ETX
 *  - StxNoext: STX + payload
 */
enum LrcMode: string
{
    case Stx = 'stx';
    case Std = 'std';
    case Noext = 'noext';
    case StxNoext = 'stx_noext';

    public static function fromConfig(string $value): self
    {
        return self::tryFrom(strtolower($value)) ?? self::Std;
    }
}
