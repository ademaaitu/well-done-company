<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learning_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('user_name');
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->string('stress_context')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_name', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_sessions');
    }
};
