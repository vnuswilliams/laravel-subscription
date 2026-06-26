<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscriptions.tables.subscription_usages', 'subscription_usages'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained(
                config('subscriptions.tables.subscriptions', 'subscriptions')
            )->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained(
                config('subscriptions.tables.plan_features', 'plan_features')
            )->cascadeOnDelete();
            $table->unsignedBigInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['subscription_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscriptions.tables.subscription_usages', 'subscription_usages'));
    }
};
