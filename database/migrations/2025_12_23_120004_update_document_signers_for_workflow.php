<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->string('status')->default('queued')->after('signer_index');
            $table->string('assigned_by_user_id')->nullable()->after('status');
            $table->timestamp('assigned_at')->nullable()->after('assigned_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn(['status', 'assigned_by_user_id', 'assigned_at']);
        });
    }
};
