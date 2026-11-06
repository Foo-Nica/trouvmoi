<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('token_hash', 64)->unique(); // jeton du cookie invité, haché
            $table->string('phone', 30)->nullable()->index();
            $table->string('locale', 5)->default('fr');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->string('notice_version', 20);
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('accepted_at');
            $table->index(['guest_id', 'event_id']);
        });

        Schema::create('folders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('guest_id')->constrained()->cascadeOnDelete();
            $table->string('access_token', 48)->unique(); // lien privé du dossier
            $table->string('name')->nullable();
            $table->json('query_face_ids')->nullable(); // visages de référence, pour la mise à jour automatique
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('folder_photo', function (Blueprint $table) {
            $table->foreignUlid('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('photo_id')->constrained()->cascadeOnDelete();
            $table->decimal('similarity', 5, 4)->nullable();
            $table->string('source', 10)->default('auto'); // auto | manual
            $table->boolean('excluded')->default(false);   // « Ce n'est pas moi »
            $table->timestamps();
            $table->primary(['folder_id', 'photo_id']);
        });

        Schema::create('face_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 10); // gallery | selfie
            $table->string('source_face_id', 64)->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('folder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10); // photo | zip
            $table->unsignedInteger('photos_count')->default(1);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('removal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('photo_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 10)->default('pending')->index(); // pending | done | rejected
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['removal_requests', 'downloads', 'face_searches', 'folder_photo', 'folders', 'consent_logs', 'guests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
