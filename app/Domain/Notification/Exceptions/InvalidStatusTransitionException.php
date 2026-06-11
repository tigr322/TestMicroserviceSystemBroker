<?php

declare(strict_types=1);

namespace App\Domain\Notification\Exceptions;

use RuntimeException;

final class InvalidStatusTransitionException extends RuntimeException {}
