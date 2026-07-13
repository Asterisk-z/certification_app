<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization CC list: addresses copied on every certificate mail (issued
 * + revoked) for that org. NULL organization_id = platform/admin-issued
 * credentials (those with no organization).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_cc_emails', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->timestamps();

            // One address per org (NULL org = platform); MySQL treats NULLs as
            // distinct, so platform-level dupes are guarded at the app layer.
            $table->unique(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_cc_emails');
    }
};
