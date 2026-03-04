<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('session_events', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->string('event_name');
            $table->json('payload')->nullable();
            $table->timestamp('event_at');
            $table->timestamps();

            $table->index(['session_id', 'event_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_events');
    }
};
