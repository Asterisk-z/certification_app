<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Per-organization usage caps, keyed by Organization::LIMITS. A
            // positive integer caps that resource; a missing/null entry means
            // unlimited. Kept separate from `features` (on/off gating) so a
            // resource can be both enabled and capped.
            $table->json('limits')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('limits');
        });
    }
};
