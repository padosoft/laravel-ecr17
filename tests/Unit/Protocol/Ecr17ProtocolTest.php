<?php

declare(strict_types=1);

use Padosoft\Ecr17\Protocol\Ecr17Protocol;

const T = '12345678'; // terminal id
const C = '87654321'; // cash register id
const FIELD_SEP = "\x1B";

// --- Payment family (167 bytes) --------------------------------------------

test('extended payment layout and flags', function () {
    $m = Ecr17Protocol::buildExtendedPaymentMessage(T, C, 650, '2', true, true, 'ABC');
    expect(strlen($m))->toBe(167)
        ->and(substr($m, 0, 8))->toBe(T)
        ->and($m[8])->toBe('0')
        ->and($m[9])->toBe('X')
        ->and(substr($m, 10, 8))->toBe(C)
        ->and($m[18])->toBe('1')                 // withAdditionalData
        ->and(substr($m, 19, 2))->toBe('00')
        ->and($m[21])->toBe('1')                 // cardAlreadyPresent
        ->and($m[22])->toBe('2')                 // payment type
        ->and(substr($m, 23, 8))->toBe('00000650')
        ->and(substr($m, 31, 125))->toBe(str_repeat(' ', 125))
        ->and(substr($m, 156, 3))->toBe('ABC')
        ->and(substr($m, 159, 8))->toBe('00000000');
});

test('pre-auth uses lowercase code p', function () {
    $m = Ecr17Protocol::buildPreAuthMessage(T, C, 1000);
    expect(strlen($m))->toBe(167)->and($m[9])->toBe('p')->and(substr($m, 23, 8))->toBe('00001000');
});

test('payment defaults match the basic layout', function () {
    $m = Ecr17Protocol::buildPaymentMessage(T, C, 650);
    expect(strlen($m))->toBe(167)
        ->and($m[9])->toBe('P')
        ->and($m[18])->toBe('0')   // no additional data
        ->and($m[21])->toBe('0')   // card not present
        ->and($m[22])->toBe('0')   // auto payment type
        ->and(substr($m, 31, 128))->toBe(str_repeat(' ', 128));
});

// --- Pre-auth integration / closure (176 bytes) ----------------------------

test('incremental layout', function () {
    $m = Ecr17Protocol::buildIncrementalMessage(T, C, 1000, '123456789');
    expect(strlen($m))->toBe(176)
        ->and($m[9])->toBe('i')
        ->and(substr($m, 19, 4))->toBe('0000')
        ->and(substr($m, 23, 8))->toBe('00001000')
        ->and(substr($m, 159, 9))->toBe('123456789')
        ->and(substr($m, 168, 8))->toBe('00000000');
});

test('pre-auth closure layout', function () {
    $m = Ecr17Protocol::buildPreAuthClosureMessage(T, C, 500, '000000042');
    expect(strlen($m))->toBe(176)->and($m[9])->toBe('c')->and(substr($m, 159, 9))->toBe('000000042');
});

// --- Card verification (39 bytes) ------------------------------------------

test('card verification layout', function () {
    $m = Ecr17Protocol::buildCardVerificationMessage(T, C, '1');
    expect(strlen($m))->toBe(39)
        ->and($m[9])->toBe('H')
        ->and(substr($m, 10, 8))->toBe(C)
        ->and($m[18])->toBe('0')
        ->and(substr($m, 19, 2))->toBe('00')
        ->and($m[21])->toBe('0')
        ->and($m[22])->toBe('1')
        ->and(substr($m, 23, 16))->toBe(str_repeat('0', 16));
});

// --- Session commands (26 bytes) -------------------------------------------

test('close session layout', function () {
    $m = Ecr17Protocol::buildCloseSessionMessage(T, C);
    expect(strlen($m))->toBe(26)
        ->and($m[9])->toBe('C')
        ->and(substr($m, 10, 8))->toBe(C)
        ->and($m[18])->toBe('0')
        ->and(substr($m, 19, 7))->toBe(str_repeat('0', 7));
});

test('totals layout', function () {
    $m = Ecr17Protocol::buildTotalsMessage(T, C);
    expect(strlen($m))->toBe(26)->and($m[9])->toBe('T');
});

// --- Send last result (22 bytes) -------------------------------------------

test('send last result layout', function () {
    $m = Ecr17Protocol::buildSendLastResultMessage(T, C);
    expect(strlen($m))->toBe(22)->and($m[9])->toBe('G')->and(substr($m, 19, 3))->toBe('000');
});

// --- Enable/disable ECR printing (11 bytes) --------------------------------

test('enable ECR printing layout', function () {
    expect(Ecr17Protocol::buildEnableEcrPrintMessage(T, true))->toBe('123456780E1')
        ->and(Ecr17Protocol::buildEnableEcrPrintMessage(T, false))->toBe('123456780E0');
});

// --- Reprint (22 bytes) -----------------------------------------------------

test('reprint layout', function () {
    $m = Ecr17Protocol::buildReprintMessage(T, true);
    expect(strlen($m))->toBe(22)
        ->and($m[9])->toBe('R')
        ->and($m[10])->toBe('1')   // send to ECR
        ->and($m[11])->toBe('0')   // ticket type default
        ->and(substr($m, 12, 10))->toBe(str_repeat('0', 10));
});

// --- VAS (variable, length-prefixed) ---------------------------------------

test('VAS layout and length prefix', function () {
    $m = Ecr17Protocol::buildVasMessage(T, C, '<x/>');
    expect(strlen($m))->toBe(30)
        ->and($m[9])->toBe('K')
        ->and(substr($m, 10, 8))->toBe(C)
        ->and(substr($m, 18, 3))->toBe('000')
        ->and($m[21])->toBe('0')
        ->and(substr($m, 22, 4))->toBe('0004') // length of "<x/>"
        ->and(substr($m, 26))->toBe('<x/>');
});

test('VAS rejects an oversized request', function () {
    Ecr17Protocol::buildVasMessage(T, C, str_repeat('x', 1025));
})->throws(InvalidArgumentException::class);

// --- Additional data / tokenization 'U' ------------------------------------

test('additional tags layout', function () {
    $content = '0COF0TRK123|0FNZ03'; // 18 chars
    $m = Ecr17Protocol::buildAdditionalTagsMessage(T, $content);
    expect(strlen($m))->toBe(36 + strlen($content) + 1)
        ->and($m[9])->toBe('U')
        ->and(substr($m, 10, 6))->toBe('000000')
        ->and(substr($m, 16, 2))->toBe('62')
        ->and(substr($m, 18, 8))->toBe('DF8D01  ') // left-justified, blank-filled
        ->and($m[26])->toBe('0')
        ->and(substr($m, 27, 4))->toBe('0000')
        ->and(substr($m, 31, 5))->toBe('00000')
        ->and(substr($m, 36, strlen($content)))->toBe($content)
        ->and($m[strlen($m) - 1])->toBe(FIELD_SEP);
});

test('additional tags rejects bad content', function () {
    expect(fn () => Ecr17Protocol::buildAdditionalTagsMessage(T, ''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Ecr17Protocol::buildAdditionalTagsMessage(T, str_repeat('x', 101)))->toThrow(InvalidArgumentException::class);
});

test('tokenization tag format', function () {
    expect(Ecr17Protocol::formatTokenizationTag(false, '1666354841608'))->toBe('0COF0TRK1666354841608|0FNZ03')
        ->and(Ecr17Protocol::formatTokenizationTag(true, 'ABC'))->toBe('0REC0TRKABC|0FNZ03')
        ->and(fn () => Ecr17Protocol::formatTokenizationTag(false, ''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Ecr17Protocol::formatTokenizationTag(false, str_repeat('x', 19)))->toThrow(InvalidArgumentException::class);
});

// --- Validation shared via leftPad -----------------------------------------

test('incremental rejects an oversized pre-auth code', function () {
    Ecr17Protocol::buildIncrementalMessage(T, C, 100, '1234567890'); // 10 > 9-byte field
})->throws(InvalidArgumentException::class);

test('pre-auth rejects a negative amount', function () {
    Ecr17Protocol::buildPreAuthMessage(T, C, -1);
})->throws(InvalidArgumentException::class);
