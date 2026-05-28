<?php

declare(strict_types=1);

use Padosoft\Ecr17\Protocol\LrcMode;
use Padosoft\Ecr17\Protocol\PacketCodec;
use Padosoft\Ecr17\Protocol\PacketType;
use Padosoft\Ecr17\Session\Ecr17Session;
use Padosoft\Ecr17\Session\SessionConfig;
use Padosoft\Ecr17\Transport\FakeTransport;

function fastConfig(): SessionConfig
{
    // Tiny timeouts keep the suite fast; FakeTransport delivers scripted replies
    // synchronously on each application send, so happy paths don't actually wait.
    return new SessionConfig(
        lrcMode: LrcMode::Std,
        ackTimeoutMs: 40,
        responseTimeoutMs: 40,
        retryCount: 2,
        retryDelayMs: 1,
    );
}

function progressFrame(string $msg20): string
{
    return chr(PacketCodec::SOH).$msg20.chr(PacketCodec::EOT);
}

function sentAck(FakeTransport $t): bool
{
    foreach ($t->sentFrames() as $f) {
        if ($f !== '' && ord($f[0]) === PacketCodec::ACK) {
            return true;
        }
    }

    return false;
}

function sentNak(FakeTransport $t): bool
{
    foreach ($t->sentFrames() as $f) {
        if ($f !== '' && ord($f[0]) === PacketCodec::NAK) {
            return true;
        }
    }

    return false;
}

const K_RESULT = '123456780E0000DATA';  // code 'E' at pos 10 -> result

const K_RECEIPT = '123456780SLINE 1';   // code 'S' at pos 10 -> receipt

test('happy path returns the result and ACKs it', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse($codec->encodeControl(PacketCodec::ACK).$codec->encodeApplication(K_RESULT));

    $session = new Ecr17Session($t, fastConfig());
    $result = $session->exchange('123456780P...');

    expect($result->type)->toBe(PacketType::Application)
        ->and($result->validLrc)->toBeTrue()
        ->and($result->payload)->toBe(K_RESULT)
        ->and($t->applicationRequestCount())->toBe(1)
        ->and(sentAck($t))->toBeTrue();
});

test('NAK triggers a retransmit then succeeds', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse($codec->encodeControl(PacketCodec::NAK)); // reply to attempt 1
    $t->enqueueResponse($codec->encodeControl(PacketCodec::ACK).$codec->encodeApplication(K_RESULT));

    $session = new Ecr17Session($t, fastConfig());
    $result = $session->exchange('123456780P...');

    expect($result->payload)->toBe(K_RESULT)->and($t->applicationRequestCount())->toBe(2);
});

test('ACK timeout exhausts retries then throws', function () {
    $t = new FakeTransport; // no responses queued
    $session = new Ecr17Session($t, fastConfig());

    expect(fn () => $session->exchange('123456780P...'))->toThrow(RuntimeException::class);
    expect($t->applicationRequestCount())->toBe(3); // initial + retryCount retransmissions
});

test('a bad-LRC response is NAKed and no valid result arrives', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $bad = $codec->encodeApplication(K_RESULT);
    $bad[strlen($bad) - 1] = chr(ord($bad[strlen($bad) - 1]) ^ 0xFF); // corrupt LRC
    $t->enqueueResponse($codec->encodeControl(PacketCodec::ACK).$bad);

    $session = new Ecr17Session($t, fastConfig());
    expect(fn () => $session->exchange('123456780P...'))->toThrow(RuntimeException::class);
    expect(sentNak($t))->toBeTrue();
});

test('progress messages are forwarded', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse(
        $codec->encodeControl(PacketCodec::ACK)
        .progressFrame('ATTENDERE PREGO     ')
        .$codec->encodeApplication(K_RESULT)
    );

    $progress = [];
    $session = new Ecr17Session($t, fastConfig());
    $session->setOnProgress(function (string $m) use (&$progress) {
        $progress[] = $m;
    });

    $result = $session->exchange('123456780P...');
    expect($result->payload)->toBe(K_RESULT)
        ->and($progress)->toBe(['ATTENDERE PREGO     ']);
});

test('receipt lines are forwarded before the result', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse(
        $codec->encodeControl(PacketCodec::ACK)
        .$codec->encodeApplication(K_RECEIPT)
        .$codec->encodeApplication(K_RESULT)
    );

    $receipts = [];
    $session = new Ecr17Session($t, fastConfig());
    $session->setOnReceiptLine(function (string $l) use (&$receipts) {
        $receipts[] = $l;
    });

    $result = $session->exchange('123456780P...');
    expect($result->payload)->toBe(K_RESULT)->and($receipts)->toBe([K_RECEIPT]);
});

test('response timeout after ACK throws', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse($codec->encodeControl(PacketCodec::ACK)); // ACK only, no result

    $session = new Ecr17Session($t, fastConfig());
    expect(fn () => $session->exchange('123456780P...'))->toThrow(RuntimeException::class);
});

test('a disconnect during the exchange throws', function () {
    $t = new FakeTransport;
    $t->disconnectOnNextRequest();
    $session = new Ecr17Session($t, fastConfig());
    expect(fn () => $session->exchange('123456780P...'))->toThrow(RuntimeException::class);
});

test('recovers and succeeds after a reconnect', function () {
    $t = new FakeTransport;
    $t->disconnectOnNextRequest();
    $session = new Ecr17Session($t, fastConfig());

    // First attempt drops.
    expect(fn () => $session->exchange('123456780P...'))->toThrow(RuntimeException::class);

    // Client reconnects the transport; the next transaction must work.
    $t->rearm();
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse($codec->encodeControl(PacketCodec::ACK).$codec->encodeApplication(K_RESULT));

    $result = $session->exchange('123456780P...');
    expect($result->type)->toBe(PacketType::Application)->and($result->payload)->toBe(K_RESULT);
});

test('sendAckOnly returns on ACK', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse($codec->encodeControl(PacketCodec::ACK));
    $session = new Ecr17Session($t, fastConfig());
    $session->sendAckOnly('123456780E1');
    expect($t->applicationRequestCount())->toBe(1);
});

test('sendAckOnly retransmits on NAK', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse($codec->encodeControl(PacketCodec::NAK));
    $t->enqueueResponse($codec->encodeControl(PacketCodec::ACK));
    $session = new Ecr17Session($t, fastConfig());
    $session->sendAckOnly('123456780E0');
    expect($t->applicationRequestCount())->toBe(2);
});

test('sendAckOnly times out', function () {
    $t = new FakeTransport; // no ACK
    $session = new Ecr17Session($t, fastConfig());
    expect(fn () => $session->sendAckOnly('123456780E1'))->toThrow(RuntimeException::class);
});

test('exchangeWithAdditionalData sends two requests', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse($codec->encodeControl(PacketCodec::ACK)); // ACK for the main 'P'
    $t->enqueueResponse($codec->encodeControl(PacketCodec::ACK).$codec->encodeApplication(K_RESULT));

    $session = new Ecr17Session($t, fastConfig());
    $result = $session->exchangeWithAdditionalData('123456780P...', '123456780U...');
    expect($result->payload)->toBe(K_RESULT)->and($t->applicationRequestCount())->toBe(2);
});

test('receipt drain forwards receipts after the result', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse(
        $codec->encodeControl(PacketCodec::ACK)
        .$codec->encodeApplication(K_RESULT)
        .$codec->encodeApplication(K_RECEIPT)
    );

    $cfg = fastConfig();
    $cfg->receiptDrainMs = 30;
    $receipts = [];
    $session = new Ecr17Session($t, $cfg);
    $session->setOnReceiptLine(function (string $l) use (&$receipts) {
        $receipts[] = $l;
    });

    $result = $session->exchange('123456780P...');
    expect($result->payload)->toBe(K_RESULT)->and($receipts)->toBe([K_RECEIPT]);
});

test('a result delivered before the ACK is not lost', function () {
    $t = new FakeTransport;
    $codec = new PacketCodec(LrcMode::Std);
    $t->enqueueResponse($codec->encodeApplication(K_RESULT)); // result, no leading ACK

    $session = new Ecr17Session($t, fastConfig());
    $result = $session->exchange('123456780P...');
    expect($result->type)->toBe(PacketType::Application)
        ->and($result->payload)->toBe(K_RESULT)
        ->and($t->applicationRequestCount())->toBe(1)
        ->and(sentAck($t))->toBeTrue();
});
