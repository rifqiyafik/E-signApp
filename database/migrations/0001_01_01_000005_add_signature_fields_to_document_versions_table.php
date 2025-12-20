<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add digital signature fields to document_versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->string('signature_algorithm')->nullable()->after('signed_pdf_size');
            $table->longText('signature_value')->nullable()->after('signature_algorithm');
            $table->string('signing_cert_fingerprint', 64)->nullable()->index()->after('signature_value');
            $table->string('signing_cert_subject')->nullable()->after('signing_cert_fingerprint');
            $table->string('signing_cert_serial')->nullable()->after('signing_cert_subject');
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn([
                'signature_algorithm',
                'signature_value',
                'signing_cert_fingerprint',
                'signing_cert_subject',
                'signing_cert_serial',
            ]);
        });
    }
};
