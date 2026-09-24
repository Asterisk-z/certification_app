<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the issuing organization on certificates created before it was
 * derived on write. An org-less certificate resolves no CC list, so its
 * issued/revoked mail silently skips the organization's copied addresses,
 * and the verification page cannot name the issuer.
 *
 * Only NULLs are filled, and only where a source actually names an org, so
 * genuinely platform-issued credentials are left alone. The template owns the
 * credential, so it wins; the recipient's org is the fallback for the
 * template-less manual entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'UPDATE certificates SET organization_id = ('.
            'SELECT organization_id FROM certificate_templates '.
            'WHERE certificate_templates.id = certificates.certificate_template_id) '.
            'WHERE organization_id IS NULL '.
            'AND certificate_template_id IS NOT NULL '.
            'AND (SELECT organization_id FROM certificate_templates '.
            'WHERE certificate_templates.id = certificates.certificate_template_id) IS NOT NULL'
        );

        DB::statement(
            'UPDATE certificates SET organization_id = ('.
            'SELECT organization_id FROM recipients '.
            'WHERE recipients.id = certificates.recipient_id) '.
            'WHERE organization_id IS NULL '.
            'AND (SELECT organization_id FROM recipients '.
            'WHERE recipients.id = certificates.recipient_id) IS NOT NULL'
        );
    }

    public function down(): void
    {
        // Irreversible by design: which rows were NULL beforehand is not
        // recorded, and the values written here are the correct ones anyway.
    }
};
