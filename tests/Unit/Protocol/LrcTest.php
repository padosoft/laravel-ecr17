<?php

declare(strict_types=1);

use Padosoft\Ecr17\Protocol\Lrc;
use Padosoft\Ecr17\Protocol\LrcMode;

// Independent reference implementation (asserts against first principles, not a
// copy of the production code). Ported from the RN gtest `test_lrc`.
$reference = function (string $payload, LrcMode $mode): int {
    $lrc = 0x7F;
    if ($mode === LrcMode::Stx || $mode === LrcMode::StxNoext) {
        $lrc ^= 0x02;
    }
    for ($i = 0, $n = strlen($payload); $i < $n; $i++) {
        $lrc ^= ord($payload[$i]);
    }
    if ($mode === LrcMode::Stx || $mode === LrcMode::Noext) {
        $lrc ^= 0x03;
    }

    return $lrc;
};

test('empty payload in STD mode is the base 0x7F', function () {
    expect(Lrc::compute('', LrcMode::Std))->toBe(0x7F);
});

test('empty payload in STX mode folds STX and ETX (0x7E)', function () {
    expect(Lrc::compute('', LrcMode::Stx))->toBe(0x7E);
});

test('known vector "A" across all modes', function () {
    expect(Lrc::compute('A', LrcMode::Std))->toBe(0x3E)
        ->and(Lrc::compute('A', LrcMode::Stx))->toBe(0x3F)
        ->and(Lrc::compute('A', LrcMode::Noext))->toBe(0x3D)
        ->and(Lrc::compute('A', LrcMode::StxNoext))->toBe(0x3C);
});

test('matches an independent reference for every mode', function () use ($reference) {
    $payload = chr(0x00).chr(0x7F).chr(0x55).chr(0xAA).'Z'.chr(0x10);
    foreach ([LrcMode::Stx, LrcMode::Std, LrcMode::Noext, LrcMode::StxNoext] as $mode) {
        expect(Lrc::compute($payload, $mode))->toBe($reference($payload, $mode));
    }
});
