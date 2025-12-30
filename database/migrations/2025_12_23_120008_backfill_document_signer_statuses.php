<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_signers')
            ->whereNotNull('signed_at')
            ->update(['status' => 'signed']);

        DB::table('document_signers')
            ->whereNull('signed_at')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '');
            })
            ->update(['status' => 'queued']);
    }

    public function down(): void
    {
        // No-op
    }
};
