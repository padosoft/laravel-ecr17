<?php

declare(strict_types=1);
use Padosoft\Ecr17\Tests\TestCase;

// Feature tests exercise the Laravel integration (ServiceProvider, config, facade)
// via Orchestra Testbench. Unit tests (the pure protocol core) need no framework.
uses(TestCase::class)->in('Feature');
