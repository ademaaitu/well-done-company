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
            $table->text('question');
            $table->json('options');
            $table->string('correct_option');
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenarios');
    }
};