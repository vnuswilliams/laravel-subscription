<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('subscriptions.tables.plans', 'plans'), function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal(
                'price',
                (int) config('subscriptions.price.precision', 12),
                (int) config('subscriptions.price.scale', 2)
            )->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('periodicity_type')->nullable()->comment('day|week|month|year — null = permanent');
            $table->unsignedSmallInteger('periodicity')->nullable()->comment('ex: 1 = 1 mois');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscriptions.tables.plans', 'plans'));
    }
};
