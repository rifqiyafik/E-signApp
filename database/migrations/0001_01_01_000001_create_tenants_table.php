<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Tenants Table
 * 
 * This is the central tenants table that stores all tenant information.
 * 
 * CUSTOMIZE: Add more columns as needed for your application.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            // Required columns
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->string('slug')->unique()->nullable();
            $table->string('plan')->default('free');
            $table->unsignedBigInteger('owner_id')->nullable()->index();

            // Tenancy data column (used by stancl/tenancy for additional data)
            $table->json('data')->nullable();

            $table->timestamps();

            // Add more custom columns here as needed
            // $table->string('theme_color')->nullable();
            // $table->string('logo')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
