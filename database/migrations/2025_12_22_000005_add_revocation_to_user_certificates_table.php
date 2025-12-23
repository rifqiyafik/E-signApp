<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_certificates', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('valid_to');
            $table->string('revoked_reason')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_certificates', function (Blueprint $table) {
            $table->dropColumn(['revoked_at', 'revoked_reason']);
        });
    }
};
