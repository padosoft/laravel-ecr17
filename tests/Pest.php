<?php

declare(strict_types=1);

// Feature tests exercise the Laravel integration (ServiceProvider, config, facade)
// via Orchestra Testbench. Unit tests (the pure protocol core) need no framework.
uses(Padosoft\Ecr17\Tests\TestCase::class)->in('Feature');
