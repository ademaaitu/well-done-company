<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_answers', function (Blueprint $table) {
            $table->boolean('wrong_explanation_shown')->default(false)->after('stress_context');
        });
    }

    public function down(): void
    {
        Schema::table('user_answers', function (Blueprint $table) {
            $table->dropColumn('wrong_explanation_shown');
        });
    }
};
