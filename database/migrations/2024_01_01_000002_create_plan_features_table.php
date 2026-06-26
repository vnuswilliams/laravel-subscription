<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscriptions.tables.plan_features', 'plan_features'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained(
                config('subscriptions.tables.plans', 'plans')
            )->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('type')->default('boolean')->comment('boolean|consumable');
            $table->unsignedBigInteger('charges')->nullable()->comment('null = illimité');
            $table->timestamps();

            $table->unique(['plan_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscriptions.tables.plan_features', 'plan_features'));
    }
};
