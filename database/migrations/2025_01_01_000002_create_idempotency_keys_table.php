<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();         // The client-supplied idempotency_key
            $table->string('request_hash', 64);       // SHA-256 of the full request payload
            $table->json('response_payload');          // Cached response for replays
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
