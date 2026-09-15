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
        Schema::create('application_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('response_time_avg')->default(0);
            $table->unsignedInteger('response_time_p95')->default(0);
            $table->json('status_codes')->nullable();
            $table->timestamp('collected_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['application_id', 'collected_at']);
            $table->index(['organization_id', 'collected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_metrics');
    }
};
