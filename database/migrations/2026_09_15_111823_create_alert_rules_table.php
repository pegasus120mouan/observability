<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('metric_type');
            $table->string('condition')->default('gt');
            $table->decimal('threshold', 12, 4)->nullable();
            $table->unsignedInteger('duration')->default(5);
            $table->string('severity')->default('high');
            $table->boolean('enabled')->default(true);
            $table->json('notification_channels')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'enabled']);
            $table->index(['metric_type', 'enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
