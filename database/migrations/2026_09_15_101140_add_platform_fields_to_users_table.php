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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->string('status')->default('active')->after('is_super_admin');
            $table->foreignId('current_organization_id')
                ->nullable()
                ->after('status')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('current_organization_id');

            $table->index('status');
            $table->index('is_super_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_organization_id');
            $table->dropIndex(['status']);
            $table->dropIndex(['is_super_admin']);
            $table->dropColumn(['is_super_admin', 'status', 'last_login_at']);
        });
    }
};
