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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('agent_uid')->unique();
            $table->string('api_key_hash');
            $table->string('version')->nullable();
            $table->string('platform')->nullable();
            $table->string('architecture')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('last_seen_at');
        });

        Schema::table('hosts', function (Blueprint $table) {
            $table->foreign('agent_id')->references('id')->on('agents')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
        });

        Schema::dropIfExists('agents');
    }
};
