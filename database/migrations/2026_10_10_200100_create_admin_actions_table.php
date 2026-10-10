<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Admin's own diary: who approved, rejected, reopened, deactivated or activated whom, and why.
 * It exists so the Admin's work can be audited. The names are copied into the row on purpose, so the
 * entry still reads correctly if an account is deleted later (the two user columns then become null).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_actions')) {
            return;
        }

        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_name', 120);
            $table->string('action', 30);
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_name', 120);
            $table->string('target_role', 20);
            $table->string('detail', 200)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
