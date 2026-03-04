<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_answers', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->nullable();
            $table->string('user_name');
            $table->foreignId('scenario_id')->constrained('scenarios')->cascadeOnDelete();
            $table->string('selected_option');
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->unsignedInteger('retries')->default(0);
            $table->string('stress_context')->nullable();
            $table->timestamps();

            $table->index(['user_name', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_answers');
    }
};
