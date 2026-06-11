<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certificate_template_id')->constrained();
            $table->foreignId('recipient_id')->constrained();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('certificate_number')->unique();
            $table->json('data')->nullable();
            $table->date('completion_date')->nullable();
            $table->date('issue_date');
            $table->date('expiry_date')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('renewed_from_id')->nullable()->constrained('certificates')->nullOnDelete();
            $table->string('pdf_path')->nullable();
            $table->string('uploaded_file_path')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->text('send_error')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['certificate_template_id', 'status']);
        });

        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('mailable_type')->nullable();
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email')->index();
            $table->string('subject')->nullable();
            $table->string('status')->default('queued')->index();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('subject');
            $table->longText('body');
            $table->foreignId('sent_by')->constrained('users');
            $table->string('audience')->default('all');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletters');
        Schema::dropIfExists('mail_logs');
        Schema::dropIfExists('certificates');
    }
};
