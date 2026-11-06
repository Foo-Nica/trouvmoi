<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 16)->unique();
            $table->date('event_date')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('access_mode', 10)->default('gallery'); // gallery | selfie | pin
            $table->string('pin_hash')->nullable();
            $table->string('status', 12)->default('draft')->index(); // draft | published | archived
            $table->boolean('watermark_enabled')->default(true);
            $table->boolean('has_minors')->default(false); // aucune indexation faciale si vrai
            $table->unsignedSmallInteger('retention_days')->default(60);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('faces_purged_at')->nullable();
            $table->unsignedInteger('photos_count')->default(0);
            $table->unsignedInteger('faces_count')->default(0);
            $table->unsignedInteger('scans_count')->default(0);
            $table->timestamps();
        });

        Schema::create('event_members', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 15)->default('assistant'); // assistant | organizer
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name')->nullable();
            $table->string('original_path');
            $table->string('preview_path')->nullable();   // 1600 px, filigranée, WebP
            $table->string('thumb_path')->nullable();     // 400 px, WebP
            $table->string('download_path')->nullable();  // version téléchargée par l'invité (filigranée si activé)
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum', 64)->nullable();
            $table->string('status', 12)->default('received')->index(); // received | processed | indexed | failed
            $table->unsignedSmallInteger('faces_count')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->text('error')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'checksum']);
            $table->index(['event_id', 'is_visible', 'taken_at']);
        });

        // Cadres des visages : les empreintes restent dans le service IA auto-hébergé.
        Schema::create('faces', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64)->unique();
            $table->unsignedInteger('x');
            $table->unsignedInteger('y');
            $table->unsignedInteger('w');
            $table->unsignedInteger('h');
            $table->decimal('det_score', 5, 4)->nullable();
            $table->decimal('sharpness', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faces');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('event_members');
        Schema::dropIfExists('events');
    }
};
