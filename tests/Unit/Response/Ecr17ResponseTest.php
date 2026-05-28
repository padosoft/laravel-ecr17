<?php

declare(strict_types=1);

use Padosoft\Ecr17\Response\Ecr17Response;
use Padosoft\Ecr17\Response\Outcome;

// Field builders mirroring the RN test helpers: alpha = left-justified, space
// padded; numeric = right-justified, zero padded. Payloads are synthesized
// field-by-field at the exact 1-based offsets from the spec response tables.
function respA(string $value, int $width): string
{
    return str_pad($value, $width, ' ', STR_PAD_RIGHT);
}

function respN(string $value, int $width): string
{
    return str_pad($value, $width, '0', STR_PAD_LEFT);
}

test('parses a positive payment', function () {
    $p = respA('12345678', 8).'0'.'E'.'00'
        .respN('4111111111', 19)
        .respA('ICC', 3).respA('ABC123', 6).'2111520'
        .'2'
        .respA('ACQ', 11).respN('42', 6).respN('99', 6);
    $r = Ecr17Response::parsePayment($p);
    expect($r->outcome)->toBe(Outcome::Ok)
        ->and($r->resultCode)->toBe('00')
        ->and($r->pan)->toBe(respN('4111111111', 19))
        ->and($r->transactionType)->toBe('ICC')
        ->and($r->authCode)->toBe('ABC123')
        ->and($r->hostDateTime)->toBe('2111520')
        ->and($r->cardType)->toBe('2')
        ->and($r->acquirerId)->toBe('ACQ')
        ->and($r->stan)->toBe('000042')
        ->and($r->onlineId)->toBe('000099')
        ->and($r->currency->applied)->toBeFalse();
});

test('parses a negative payment', function () {
    $p = respA('12345678', 8).'0'.'E'.'01'.respA('CARTA RIFIUTATA', 24)
        .respN('', 11) // reserved 37-47
        .'3'.respA('AC2', 11).respN('7', 6).respN('3', 6);
    $r = Ecr17Response::parsePayment($p);
    expect($r->outcome)->toBe(Outcome::Ko)
        ->and($r->resultCode)->toBe('01')
        ->and($r->errorDescription)->toBe('CARTA RIFIUTATA')
        ->and($r->cardType)->toBe('3')
        ->and($r->stan)->toBe('000007');
});

test('parses a payment with currency exchange (DCC)', function () {
    $base = respA('12345678', 8).'0'.'V'.'00'.respN('4111111111', 19).respA('ICC', 3)
        .respA('ABC123', 6).'2111520'.'2'.respA('ACQ', 11).respN('42', 6).respN('99', 6);
    // actionCode(3) origAmount(8) flag(1) rate(8) ccy(3) amount(12) precision(1)
    $p = $base.'000'.respN('650', 8).'1'.respN('12345', 8).'USD'.respN('650', 12).'2';
    $r = Ecr17Response::parsePayment($p);
    expect($r->outcome)->toBe(Outcome::Ok)
        ->and($r->currency->applied)->toBeTrue()
        ->and($r->currency->rate)->toBe('00012345')
        ->and($r->currency->currencyCode)->toBe('USD')
        ->and($r->currency->amount)->toBe('000000000650')
        ->and($r->currency->precision)->toBe('2');
});

test('parses status', function () {
    $p = respA('12345678', 8).'0'.'s'.respN('', 10).'0102251530'.'2'.'V1.2.3';
    $r = Ecr17Response::parseStatus($p);
    expect($r->terminalId)->toBe('12345678')
        ->and($r->dateTimeRaw)->toBe('0102251530')
        ->and($r->status)->toBe(2)
        ->and($r->softwareRelease)->toBe('V1.2.3');
});

test('parses totals', function () {
    $p = respA('12345678', 8).'0'.'T'.'00'.respN('123456', 16).respN('', 6);
    $r = Ecr17Response::parseTotals($p);
    expect($r->outcome)->toBe(Outcome::Ok)->and($r->posTotal)->toBe(respN('123456', 16));
});

test('parses a positive close', function () {
    $p = respA('12345678', 8).'0'.'C'.'00'.respN('1000', 16).respN('1000', 16);
    $r = Ecr17Response::parseClose($p);
    expect($r->outcome)->toBe(Outcome::Ok)
        ->and($r->posTotal)->toBe(respN('1000', 16))
        ->and($r->hostTotal)->toBe(respN('1000', 16));
});

test('parses a negative close', function () {
    $p = respA('12345678', 8).'0'.'C'.'01'.respA('SBILANCIO', 19).'100';
    $r = Ecr17Response::parseClose($p);
    expect($r->outcome)->toBe(Outcome::Ko)
        ->and($r->errorDescription)->toBe('SBILANCIO')
        ->and($r->actionCode)->toBe('100');
});

test('parses a positive pre-auth', function () {
    $p = respA('12345678', 8).'0'.'e'.'00'.respN('4111111111', 19).respA('CLI', 3)
        .respA('AUTH01', 6).respN('50000', 8).respN('123', 9).'000'.'2111520';
    $r = Ecr17Response::parsePreAuth($p);
    expect($r->outcome)->toBe(Outcome::Ok)
        ->and($r->transactionType)->toBe('CLI')
        ->and($r->authCode)->toBe('AUTH01')
        ->and($r->preAuthorizedAmount)->toBe('00050000')
        ->and($r->preAuthCode)->toBe('000000123')
        ->and($r->hostDateTime)->toBe('2111520');
});

test('pre-auth OK does not leak the amount digit as cardType', function () {
    $p = respA('12345678', 8).'0'.'e'.'00'.respN('4111111111', 19).respA('CLI', 3)
        .respA('AUTH01', 6).respN('50001', 8).respN('123', 9).'000'.'2111520';
    $r = Ecr17Response::parsePreAuth($p);
    expect($r->outcome)->toBe(Outcome::Ok)
        ->and($r->preAuthorizedAmount)->toBe('00050001') // ends in '1'
        ->and($r->cardType)->toBe('');                    // must stay empty
});

test('parses a VAS response', function () {
    $xml = '<ecrres><p k="RESPID">0</p><p k="RESPMSG">OK-APPROVED</p>'
        .'<p k="ORDER_ID">ABC123</p></ecrres>';
    // header(10) reserved(4) concatFlag(1) idMessage(3) filler-to-pos27(8) xml
    $p = respA('12345678', 8).'0'.'K'.respN('', 4).'0'.'001'.respN('', 8).$xml;
    $r = Ecr17Response::parseVas($p);
    expect($r->moreMessages)->toBeFalse()
        ->and($r->idMessage)->toBe('001')
        ->and($r->responseId)->toBe('0')
        ->and($r->responseMessage)->toBe('OK-APPROVED')
        ->and($r->orderId)->toBe('ABC123')
        ->and($r->rawXml)->toBe($xml);
});

test('parsing is defensive on short or empty payloads', function () {
    $r = Ecr17Response::parsePayment('');
    expect($r->outcome)->toBe(Outcome::Unknown)->and($r->resultCode)->toBe('')->and($r->pan)->toBe('');

    $s = Ecr17Response::parseStatus('123'); // truncated, must not crash
    expect($s->status)->toBe(-1);
});

test('outcome mapping', function () {
    expect(Outcome::fromCode('00'))->toBe(Outcome::Ok)
        ->and(Outcome::fromCode('01'))->toBe(Outcome::Ko)
        ->and(Outcome::fromCode('05'))->toBe(Outcome::CardNotPresent)
        ->and(Outcome::fromCode('09'))->toBe(Outcome::UnknownTag)
        ->and(Outcome::fromCode('zz'))->toBe(Outcome::Unknown);
});
