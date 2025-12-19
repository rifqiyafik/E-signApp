<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Tenant Users Table
 * 
 * This is the pivot table that connects users to tenants.
 * Stores role and tenant-specific user information.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('global_user_id');

            // Role management
            $table->string('role')->default('member'); // super_admin, admin, member, etc.
            $table->boolean('is_owner')->default(false);

            // Timestamps
            $table->timestamp('tenant_join_date')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            // Optional: Nusawork integration (uncomment if needed)
            $table->string('nusawork_id')->nullable();
            $table->boolean('is_nusawork_integrated')->default(false);
            $table->timestamp('nusawork_integrated_at')->nullable();

            // Optional: Additional user data per tenant
            // $table->string('google_id')->nullable();
            // $table->string('avatar')->nullable();

            // Indexes and constraints
            $table->unique(['tenant_id', 'global_user_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('global_user_id')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
