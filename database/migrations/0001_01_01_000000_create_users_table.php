<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users — shared identity row for Teacher/Parent/Admin.
     * Matches Table 7 of the Data Dictionary, plus email_verified_at
     * which Laravel's built-in verification system needs to track
     * whether the registration confirmation link has been clicked.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('user_type', ['Teacher', 'Parent', 'Admin']);
            $table->string('contact_number')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->rememberToken();
            // created_at from schema.sql is covered by Laravel's timestamps().
            // We don't need updated_at from the original schema, but Laravel
            // always pairs the two — harmless to have both.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
