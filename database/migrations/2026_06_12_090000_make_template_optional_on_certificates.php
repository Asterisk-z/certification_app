<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Offline certificates may be finished documents with no template;
            // the title then describes the credential.
            $table->string('title')->nullable()->after('certificate_number');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->unsignedBigInteger('certificate_template_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
