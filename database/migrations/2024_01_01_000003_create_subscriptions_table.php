<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscriptions.tables.subscriptions', 'subscriptions'), function (Blueprint $table): void {
            $table->id();
            $table->morphs('subscriber');
            $table->foreignId('plan_id')->constrained(
                config('subscriptions.tables.plans', 'plans')
            )->restrictOnDelete();
            $table->string('status')->default('active')->comment('active|on_trial|on_grace_period|canceled|expired');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable()->comment('null = permanent');
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscriptions.tables.subscriptions', 'subscriptions'));
    }
};
