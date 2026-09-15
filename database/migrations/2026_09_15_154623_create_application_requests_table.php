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
        Schema::create('application_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->timestamp('occurred_at');
            $table->string('method', 16)->nullable();
            $table->string('resource', 512);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_us')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['application_id', 'occurred_at']);
            $table->index(['organization_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_requests');
    }
};
