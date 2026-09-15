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
        Schema::table('application_requests', function (Blueprint $table) {
            $table->string('client_ip', 45)->nullable()->after('duration_us');
            $table->string('geo_country', 2)->nullable()->after('client_ip');
            $table->string('geo_city', 80)->nullable()->after('geo_country');
            $table->decimal('geo_lat', 8, 4)->nullable()->after('geo_city');
            $table->decimal('geo_lng', 8, 4)->nullable()->after('geo_lat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_requests', function (Blueprint $table) {
            $table->dropColumn(['client_ip', 'geo_country', 'geo_city', 'geo_lat', 'geo_lng']);
        });
    }
};
