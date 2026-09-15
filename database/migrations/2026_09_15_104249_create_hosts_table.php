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
        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('hostname');
            $table->string('display_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('operating_system')->nullable();
            $table->string('os_version')->nullable();
            $table->string('architecture')->nullable();
            $table->string('environment')->default('production');
            $table->string('status')->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('hostname');
            $table->index('status');
            $table->index('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hosts');
    }
};
