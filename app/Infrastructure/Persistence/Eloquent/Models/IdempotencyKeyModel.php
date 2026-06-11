<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for idempotency_keys table.
 * Acts as a durable fallback behind the Redis layer.
 *
 * @property int             $id
 * @property string          $key
 * @property string          $request_hash
 * @property array           $response_payload
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon  $created_at
 */
final class IdempotencyKeyModel extends Model
{
    protected $table = 'idempotency_keys';

    public $timestamps = false;

    protected $fillable = [
        'key',
        'request_hash',
        'response_payload',
        'expires_at',
    ];

    protected $casts = [
        'response_payload' => 'array',
        'expires_at'       => 'datetime',
        'created_at'       => 'datetime',
    ];
}
