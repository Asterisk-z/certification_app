<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add the tenant key to every org-scoped table. Existing rows stay NULL =
 * admin-owned. organization_id is denormalised onto template_blocks (derivable
 * from the template) so the tenancy global scope never needs a join.
 */
return new class extends Migration
{
    private array $tables = [
        'certificate_templates',
        'template_blocks',
        'groups',
        'recipients',
        'certificates',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')
                    ->constrained()->nullOnDelete();
            });
        }

        // Keep blocks aligned with their template's org (a no-op today since all
        // rows are admin-owned, but correct if run against migrated data).
        DB::statement(
            'UPDATE template_blocks SET organization_id = ('.
            'SELECT organization_id FROM certificate_templates '.
            'WHERE certificate_templates.id = template_blocks.certificate_template_id)'
        );
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }
    }
};
