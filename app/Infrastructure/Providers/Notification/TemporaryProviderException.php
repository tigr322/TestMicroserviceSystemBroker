<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers\Notification;

use RuntimeException;

/**
 * Thrown when the notification provider encounters a transient error
 * (network timeout, rate-limit, 5xx response).
 * The queue job will automatically retry with exponential back-off.
 */
final class TemporaryProviderException extends RuntimeException {}
