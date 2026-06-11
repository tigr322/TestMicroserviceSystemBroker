<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('recipient_id')->index();
            $table->string('channel', 10); // email | sms
            $table->string('priority', 10); // high | normal | low
            $table->text('message');
            $table->string('status', 20)->default('queued')->index(); // queued | sent | delivered | failed
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->string('idempotency_key')->nullable()->index();
            $table->timestamps();

            $table->index(['recipient_id', 'status']);
            $table->index(['channel', 'priority', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
