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
        Schema::table('job_orders', function (Blueprint $table) {
            // Change integer foreign keys to UUID to match the referenced tables
            $table->uuid('customer_id')->nullable()->change();
            $table->uuid('workshop_id')->nullable()->change();
            $table->uuid('vehicle_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->integer('customer_id')->nullable()->change();
            $table->integer('workshop_id')->nullable()->change();
            $table->integer('vehicle_id')->nullable()->change();
        });
    }
};
