<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recipient email and template code are unique per organization, not globally,
 * so the same person/code can exist under different orgs. Per-org uniqueness is
 * enforced in application validation (a composite DB unique can't enforce it:
 * SQLite and MySQL both treat NULL organization_id as distinct). Here we just
 * drop the global uniques and add plain lookup indexes.
 *
 * certificate_number stays globally unique — it is the public verifier key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->dropUnique('recipients_email_unique');
            $table->index(['organization_id', 'email']);
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropUnique('certificate_templates_code_unique');
            $table->index(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'email']);
            $table->unique('email');
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'code']);
            $table->unique('code');
        });
    }
};
