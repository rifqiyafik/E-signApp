<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create User Certificates Table (Central)
 *
 * Stores user public/private key material and X.509 certificate metadata.
 * Private key is encrypted at rest using Laravel Crypt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_certificates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('global_user_id')->unique();

            $table->text('public_key');
            $table->text('certificate');
            $table->string('certificate_fingerprint', 64)->index();
            $table->string('certificate_serial')->nullable();
            $table->string('certificate_subject')->nullable();
            $table->string('certificate_issuer')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();

            $table->text('private_key_encrypted');
            $table->text('private_key_passphrase_encrypted')->nullable();
            $table->string('key_algorithm')->default('RSA');
            $table->string('signature_algorithm')->default('sha256WithRSAEncryption');
            $table->timestamps();

            $table->foreign('global_user_id')
                ->references('global_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_certificates');
    }
};
