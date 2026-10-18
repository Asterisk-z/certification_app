<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_logs', function (Blueprint $table) {
            // Serialized mailable (base64) so a queued/failed row can be re-sent as-is.
            $table->longText('payload')->nullable()->after('error');
            $table->unsignedSmallInteger('attempts')->default(0)->after('payload');
            $table->timestamp('last_attempted_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('mail_logs', function (Blueprint $table) {
            $table->dropColumn(['payload', 'attempts', 'last_attempted_at']);
        });
    }
};
