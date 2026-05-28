<?php

declare(strict_types=1);

namespace Padosoft\Ecr17\Transport;

use RuntimeException;

/** Thrown when the transport connection is lost (drop/EOF/socket error). */
final class TransportException extends RuntimeException {}
