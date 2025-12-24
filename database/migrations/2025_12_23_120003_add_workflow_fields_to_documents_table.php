<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('status')->default('completed')->after('mime_type');
            $table->unsignedInteger('current_signer_index')->nullable()->after('status');
            $table->string('draft_file_disk')->nullable()->after('current_signer_index');
            $table->string('draft_file_path')->nullable()->after('draft_file_disk');
            $table->string('draft_sha256', 64)->nullable()->after('draft_file_path');
            $table->timestamp('draft_uploaded_at')->nullable()->after('draft_sha256');
            $table->timestamp('expires_at')->nullable()->after('draft_uploaded_at');
            $table->timestamp('canceled_at')->nullable()->after('expires_at');
            $table->string('canceled_by_user_id')->nullable()->after('canceled_at');
            $table->timestamp('status_updated_at')->nullable()->after('canceled_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'current_signer_index',
                'draft_file_disk',
                'draft_file_path',
                'draft_sha256',
                'draft_uploaded_at',
                'expires_at',
                'canceled_at',
                'canceled_by_user_id',
                'status_updated_at',
            ]);
        });
    }
};
