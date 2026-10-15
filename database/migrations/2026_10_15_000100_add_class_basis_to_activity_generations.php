<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Write it for my class": a Generate request can be based on one class's real readings (see
 * App\Support\GenerationBasis). The request keeps which class and what it was based on, and each
 * activity it writes keeps the same lines, so a teacher can see later WHY a text looks the way it does.
 * Everything is nullable: a request with no class, and every activity made before this, stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_generations', function (Blueprint $table) {
            $table->unsignedBigInteger('class_id')->nullable()->after('teacher_id');
            $table->json('basis')->nullable()->after('teacher_notes');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->json('generation_basis')->nullable()->after('teacher_notes');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('generation_basis');
        });

        Schema::table('activity_generations', function (Blueprint $table) {
            $table->dropColumn(['class_id', 'basis']);
        });
    }
};
