<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers\Notification;

use RuntimeException;

/**
 * Thrown when the notification provider encounters an unrecoverable error
 * (invalid recipient, blocked address, hard bounce).
 * The job must NOT be retried — the notification is immediately marked as failed.
 */
final class PermanentProviderException extends RuntimeException {}
