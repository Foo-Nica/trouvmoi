<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('type', 10)->default('public')->after('title'); // public | client
            $table->string('client_name', 150)->nullable()->after('type');
            $table->unsignedInteger('original_price')->nullable()->after('watermark_enabled'); // FCFA par photo originale
            $table->unsignedInteger('pack_price')->nullable()->after('original_price');       // FCFA pour toutes les photos
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->string('hd_path')->nullable()->after('download_path'); // version originale, petit logo discret
            $table->unsignedInteger('price')->nullable()->after('is_visible'); // prix propre à la photo (sinon celui de l'événement)
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('access_token', 64)->unique();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 30);
            $table->string('customer_email', 150)->nullable();
            $table->boolean('is_pack')->default(false);
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('XOF');
            $table->string('status', 10)->default('pending')->index(); // pending | paid | failed
            $table->string('gateway', 12);
            $table->string('gateway_reference', 100)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('photo_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->timestamps();
            $table->unique(['order_id', 'photo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['hd_path', 'price']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['type', 'client_name', 'original_price', 'pack_price']);
        });
    }
};
