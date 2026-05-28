<?php

declare(strict_types=1);

use Padosoft\Ecr17\Protocol\LrcMode;
use Padosoft\Ecr17\Protocol\PacketCodec;
use Padosoft\Ecr17\Protocol\PacketType;

const SOH = 0x01;
const STX = 0x02;
const ETX = 0x03;
const EOT = 0x04;
const ACK = 0x06;
const NAK = 0x15;

test('encodeApplication frames STX + payload + ETX + LRC', function () {
    $frame = (new PacketCodec(LrcMode::Std))->encodeApplication('AB');
    expect(strlen($frame))->toBe(5)
        ->and(ord($frame[0]))->toBe(STX)
        ->and($frame[1])->toBe('A')
        ->and($frame[2])->toBe('B')
        ->and(ord($frame[3]))->toBe(ETX)
        ->and(ord($frame[4]))->toBe(0x7C); // 0x7F ^ 'A' ^ 'B'
});

test('application frame round-trips in every LRC mode', function () {
    foreach ([LrcMode::Stx, LrcMode::Std, LrcMode::Noext, LrcMode::StxNoext] as $mode) {
        $codec = new PacketCodec($mode);
        $payload = '123456780P0000065000';
        $decoded = $codec->decode($codec->encodeApplication($payload));
        expect($decoded->type)->toBe(PacketType::Application)
            ->and($decoded->payload)->toBe($payload)
            ->and($decoded->validLrc)->toBeTrue();
    }
});

test('a corrupted LRC is detected', function () {
    $codec = new PacketCodec(LrcMode::Std);
    $frame = $codec->encodeApplication('HELLO');
    $frame[strlen($frame) - 1] = chr(ord($frame[strlen($frame) - 1]) ^ 0xFF); // corrupt LRC
    $decoded = $codec->decode($frame);
    expect($decoded->type)->toBe(PacketType::Application)
        ->and($decoded->payload)->toBe('HELLO')
        ->and($decoded->validLrc)->toBeFalse();
});

test('encodeControl frames ctrl + ETX + LRC', function () {
    $frame = (new PacketCodec(LrcMode::Std))->encodeControl(ACK);
    expect(strlen($frame))->toBe(3)
        ->and(ord($frame[0]))->toBe(ACK)
        ->and(ord($frame[1]))->toBe(ETX);
});

test('decodes ACK', function () {
    $decoded = (new PacketCodec(LrcMode::Std))->decode(chr(ACK));
    expect($decoded->type)->toBe(PacketType::Ack)->and($decoded->validLrc)->toBeTrue();
});

test('decodes NAK', function () {
    $decoded = (new PacketCodec(LrcMode::Std))->decode(chr(NAK));
    expect($decoded->type)->toBe(PacketType::Nak)->and($decoded->validLrc)->toBeTrue();
});

test('empty buffer is UNKNOWN', function () {
    $decoded = (new PacketCodec(LrcMode::Std))->decode('');
    expect($decoded->type)->toBe(PacketType::Unknown)->and($decoded->validLrc)->toBeFalse();
});

test('a lone SOH byte is UNKNOWN (not a crash)', function () {
    $decoded = (new PacketCodec(LrcMode::Std))->decode(chr(SOH));
    expect($decoded->type)->toBe(PacketType::Unknown)->and($decoded->validLrc)->toBeFalse();
});

test('decodes a progress update (SOH + 20 chars + EOT)', function () {
    $msg = 'ELABORAZIONE...     '; // 20 chars per spec
    $frame = chr(SOH).$msg.chr(EOT);
    $decoded = (new PacketCodec(LrcMode::Std))->decode($frame);
    expect($decoded->type)->toBe(PacketType::Progress)->and($decoded->payload)->toBe($msg);
});

test('STX without ETX is UNKNOWN', function () {
    $decoded = (new PacketCodec(LrcMode::Std))->decode(chr(STX).'AB');
    expect($decoded->type)->toBe(PacketType::Unknown)->and($decoded->validLrc)->toBeFalse();
});

test('STX with ETX but no trailing LRC is UNKNOWN', function () {
    $decoded = (new PacketCodec(LrcMode::Std))->decode(chr(STX).'A'.chr(ETX));
    expect($decoded->type)->toBe(PacketType::Unknown)->and($decoded->validLrc)->toBeFalse();
});

test('an unknown lead byte is UNKNOWN', function () {
    $decoded = (new PacketCodec(LrcMode::Std))->decode(chr(0x99).chr(0x00));
    expect($decoded->type)->toBe(PacketType::Unknown)->and($decoded->validLrc)->toBeFalse();
});

test('trailing bytes after the LRC are UNKNOWN', function () {
    $codec = new PacketCodec(LrcMode::Std);
    $frame = $codec->encodeApplication('AB').chr(0x00); // stray trailing byte
    $decoded = $codec->decode($frame);
    expect($decoded->type)->toBe(PacketType::Unknown)->and($decoded->validLrc)->toBeFalse();
});

test('two coalesced frames are UNKNOWN (framing is the transport job)', function () {
    $codec = new PacketCodec(LrcMode::Std);
    $frame = $codec->encodeApplication('AB').$codec->encodeApplication('CD');
    $decoded = $codec->decode($frame);
    expect($decoded->type)->toBe(PacketType::Unknown)->and($decoded->validLrc)->toBeFalse();
});

test('a SOH frame not terminated by EOT is UNKNOWN', function () {
    $frame = chr(SOH).'ELABORAZIONE...     '.chr(0xFF); // wrong terminator
    $decoded = (new PacketCodec(LrcMode::Std))->decode($frame);
    expect($decoded->type)->toBe(PacketType::Unknown)->and($decoded->validLrc)->toBeFalse();
});
