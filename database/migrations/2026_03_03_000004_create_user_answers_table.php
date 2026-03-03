<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('results')->cascadeOnDelete();
            $table->foreignId('scenario_id')->constrained('scenarios')->cascadeOnDelete();
            $table->string('selected_option');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_answers');
    }
};