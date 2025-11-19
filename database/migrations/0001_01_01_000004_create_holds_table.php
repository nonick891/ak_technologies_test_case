<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('slot_id')
                ->constrained('slots')
                ->cascadeOnDelete();
            $table->string('status', 32);
            $table->uuid('idempotency_key')->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};
