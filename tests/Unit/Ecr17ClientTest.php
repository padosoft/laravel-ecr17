<?php

declare(strict_types=1);

use Padosoft\Ecr17\Ecr17Client;
use Padosoft\Ecr17\Ecr17Config;
use Padosoft\Ecr17\Protocol\LrcMode;
use Padosoft\Ecr17\Protocol\PacketCodec;
use Padosoft\Ecr17\Response\Outcome;
use Padosoft\Ecr17\Transport\FakeTransport;

// respA() / respN() are defined in Ecr17ResponseTest.php (Pest loads all files).

function clientConfig(): Ecr17Config
{
    return new Ecr17Config(
        host: '127.0.0.1',
        terminalId: '12345678',
        cashRegisterId: '87654321',
        autoReconnect: true,
        ackTimeoutMs: 40,
        responseTimeoutMs: 40,
        retryCount: 2,
        retryDelayMs: 1,
    );
}

/** ACK + an application frame carrying $payload (LrcMode::Std). */
function reply(string $payload): string
{
    $codec = new PacketCodec(LrcMode::Std);

    return $codec->encodeControl(PacketCodec::ACK).$codec->encodeApplication($payload);
}

test('status command builds, exchanges and parses', function () {
    $t = new FakeTransport;
    $client = new Ecr17Client($t, clientConfig());
    $client->connect();

    $statusPayload = respA('12345678', 8).'0'.'s'.respN('', 10).'0102251530'.'2'.'V1.2.3';
    $t->enqueueResponse(reply($statusPayload));

    $r = $client->status();
    expect($r->terminalId)->toBe('12345678')
        ->and($r->status)->toBe(2)
        ->and($r->softwareRelease)->toBe('V1.2.3');
});

test('pay command returns a parsed positive result', function () {
    $t = new FakeTransport;
    $client = new Ecr17Client($t, clientConfig());
    $client->connect();

    $payPayload = respA('12345678', 8).'0'.'E'.'00'.respN('4111111111', 19).respA('ICC', 3)
        .respA('ABC123', 6).'2111520'.'2'.respA('ACQ', 11).respN('42', 6).respN('99', 6);
    $t->enqueueResponse(reply($payPayload));

    $r = $client->pay(650, 'credit');
    expect($r->outcome)->toBe(Outcome::Ok)->and($r->stan)->toBe('000042');
    // 'P' payment is 167 bytes and was sent once.
    expect($t->applicationRequestCount())->toBe(1);
});

test('MONEY-SAFETY: a financial command is not retried after a drop', function () {
    $t = new FakeTransport;
    $client = new Ecr17Client($t, clientConfig());
    $client->connect();

    $t->disconnectOnNextRequest(); // the verifyCard send will drop the socket

    expect(fn () => $client->verifyCard('auto'))->toThrow(RuntimeException::class);
    // It must NOT have been replayed (financial): exactly one application send.
    expect($t->applicationRequestCount())->toBe(1);
});

test('a safe command is retried after a reconnect and succeeds', function () {
    $t = new FakeTransport;
    $client = new Ecr17Client($t, clientConfig());
    $client->connect();

    $t->disconnectOnNextRequest();              // first totals send drops
    $totalsPayload = respA('12345678', 8).'0'.'T'.'00'.respN('123456', 16).respN('', 6);
    $t->enqueueResponse(reply($totalsPayload)); // delivered on the retry send

    $r = $client->totals();
    expect($r->outcome)->toBe(Outcome::Ok)
        ->and($r->posTotal)->toBe(respN('123456', 16))
        ->and($t->applicationRequestCount())->toBe(2); // dropped + retried
});

test('a stale (peer-closed) socket is reconnected PROACTIVELY before a financial command', function () {
    $t = new FakeTransport;
    $client = new Ecr17Client($t, clientConfig());
    $client->connect();

    // The terminal closed the socket between transactions: still "connected" but dead.
    $t->simulatePeerClosed();

    $states = [];
    $client->setOnConnectionStateChange(function (string $s) use (&$states) {
        $states[] = $s;
    });

    $payPayload = respA('12345678', 8).'0'.'E'.'00'.respN('4111111111', 19).respA('ICC', 3)
        .respA('ABC123', 6).'2111520'.'2'.respA('ACQ', 11).respN('42', 6).respN('99', 6);
    $t->enqueueResponse(reply($payPayload)); // delivered on the freshly reconnected socket

    // The financial command must SUCCEED (no false "disconnected"): the stale
    // socket is detected and reconnected BEFORE the request is sent.
    $r = $client->pay(650);
    expect($r->outcome)->toBe(Outcome::Ok)
        ->and($states)->toContain('connecting') // proactive reconnect happened first
        ->and($t->applicationRequestCount())->toBe(1); // sent once, on the live socket
});

test('connection state changes are emitted', function () {
    $t = new FakeTransport;
    $client = new Ecr17Client($t, clientConfig());
    $states = [];
    $client->setOnConnectionStateChange(function (string $s) use (&$states) {
        $states[] = $s;
    });

    $client->connect();
    expect($states)->toBe(['connecting', 'connected']);
});
