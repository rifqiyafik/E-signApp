<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->timestamp('tsa_signed_at')->nullable()->after('signed_at');
            $table->longText('tsa_token')->nullable()->after('tsa_signed_at');
            $table->json('ltv_snapshot')->nullable()->after('tsa_token');
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn(['tsa_signed_at', 'tsa_token', 'ltv_snapshot']);
        });
    }
};
