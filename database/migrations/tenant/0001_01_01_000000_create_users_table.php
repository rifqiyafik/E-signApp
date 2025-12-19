<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Users Table (Tenant)
 * 
 * This table stores users within each tenant database.
 * Data is synced from central users table via ResourceSyncing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('global_id')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();

            // Tenant-specific fields
            $table->string('tenant_id')->nullable();
            $table->string('role')->default('member');
            $table->boolean('is_owner')->default(false);
            $table->timestamp('tenant_join_date')->nullable();

            // OAuth (optional)
            $table->string('google_id')->nullable();

            // Optional: Nusawork integration (uncomment if needed)
            $table->string('nusawork_id')->nullable();
            $table->boolean('is_nusawork_integrated')->default(false);

            // Login tracking
            $table->string('last_login_ip')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->text('last_login_user_agent')->nullable();

            // For spatie/permission
            $table->string('guard_name')->default('api');

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
    }
};
