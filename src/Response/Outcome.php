<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Response;

enum Outcome: string
{
    case Ok = 'ok';                       // "00"
    case Ko = 'ko';                       // "01"
    case CardNotPresent = 'cardNotPresent'; // "05"
    case UnknownTag = 'unknownTag';       // "09"
    case Unknown = 'unknown';             // anything else / missing

    public static function fromCode(string $code): self
    {
        return match ($code) {
            '00' => self::Ok,
            '01' => self::Ko,
            '05' => self::CardNotPresent,
            '09' => self::UnknownTag,
            default => self::Unknown,
        };
    }
}
