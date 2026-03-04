<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('branching_id');
            $table->string('stress_context')->nullable();
            $table->text('question');
            $table->json('options');
            $table->string('correct_answer');
            $table->text('wrong_explanation')->nullable();
            $table->json('next_branching_map')->nullable();
            $table->json('branching_logic')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['module_id', 'branching_id']);
            $table->index(['module_id', 'stress_context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenarios');
    }
};
