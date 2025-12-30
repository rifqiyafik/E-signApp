<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('chain_id')->unique();
            $table->string('original_filename');
            $table->string('mime_type');
            $table->string('created_by_tenant_id')->nullable();
            $table->string('created_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('file_disk');
            $table->string('file_path');
            $table->string('signed_pdf_sha256', 64)->index();
            $table->unsignedBigInteger('signed_pdf_size')->nullable();
            $table->string('verification_url')->nullable();
            $table->string('idempotency_key')->nullable()->index();
            $table->string('tenant_id')->index();
            $table->string('user_id')->index();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('document_signers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUlid('version_id')->constrained('document_versions')->cascadeOnDelete();
            $table->unsignedInteger('signer_index');
            $table->string('tenant_id')->index();
            $table->string('user_id')->index();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'signer_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signers');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
