<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('background_image')->nullable();
            $table->unsignedInteger('bg_width')->default(1123);
            $table->unsignedInteger('bg_height')->default(794);
            $table->unsignedInteger('duration')->nullable();
            $table->string('duration_type')->nullable();
            $table->unsignedBigInteger('counter')->default(0);
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('template_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('certificate_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default('text');
            $table->text('value')->nullable();
            $table->boolean('is_dynamic')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_default')->default(false);
            $table->decimal('pos_x', 8, 2)->default(0);
            $table->decimal('pos_y', 8, 2)->default(0);
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->string('font_family')->default('Arial');
            $table->unsignedSmallInteger('font_size')->default(60);
            $table->string('font_color', 7)->default('#000000');
            $table->string('font_weight')->default('normal');
            $table->string('text_align')->default('left');
            $table->timestamps();
            $table->unique(['certificate_template_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_blocks');
        Schema::dropIfExists('certificate_templates');
    }
};
