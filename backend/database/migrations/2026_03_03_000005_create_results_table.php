<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('session_id')->nullable();
            $table->string('stress_context')->nullable();
            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedInteger('accuracy_score')->default(0);
            $table->unsignedInteger('reaction_risk_index')->default(0);
            $table->unsignedInteger('stress_response_score')->default(0);
            $table->unsignedInteger('overall_preparedness_percent')->default(0);
            $table->string('risk_category')->default('Moderate');
            $table->text('behavioral_analysis')->nullable();
            $table->json('personalized_checklist')->nullable();
            $table->text('recommendation')->nullable();
            $table->json('progress')->nullable();
            $table->json('session_json')->nullable();
            $table->timestamps();

            $table->index(['user_name', 'module_id']);
            $table->index('risk_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
