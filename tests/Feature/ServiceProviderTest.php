<?php

declare(strict_types=1);

test('the package config is merged', function () {
    expect(config('ecr17.port'))->toBe(1024)
        ->and(config('ecr17.lrc_mode'))->toBe('std')
        ->and(config('ecr17.response_timeout_ms'))->toBe(60000);
});
