<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row for every time a parent presses "Go to test payment" for a paid activity (config/payments.php). The row is the record of the
     * attempt: it is made BEFORE the parent leaves for the provider's page, and it is only ever moved to "paid" by a message from the
     * provider (or by asking the provider), never by the page the parent comes back to.
     *
     * Statuses: pending (asked, nothing final), paid (the provider said so and the reference and amount match), cancelled (the parent chose to
     * stop), expired (the time ran out), failed (could not be started). Card, wallet and bank details are never stored: they are typed on the
     * provider's page. `is_test` marks every payment made in test mode.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('open_repository_listings')->cascadeOnDelete();
            $table->string('provider', 20)->default('paymongo');
            $table->string('provider_session_id', 80)->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->unsignedInteger('amount_centavos');
            $table->string('currency', 3)->default('PHP');
            $table->string('method', 20)->nullable();
            $table->string('provider_payment_id', 80)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('failure_reason', 160)->nullable();
            $table->boolean('is_test')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'status']);
            $table->index(['learner_id', 'listing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
