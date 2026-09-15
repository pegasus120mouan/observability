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
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('log_sources')->cascadeOnDelete();
            $table->timestamp('logged_at');
            $table->string('level', 16);
            $table->text('message');
            $table->string('source')->nullable();
            $table->string('facility')->nullable();
            $table->string('event_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('username')->nullable();
            $table->string('process')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'logged_at']);
            $table->index(['host_id', 'logged_at']);
            $table->index(['source_id', 'logged_at']);
            $table->index(['level', 'logged_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
