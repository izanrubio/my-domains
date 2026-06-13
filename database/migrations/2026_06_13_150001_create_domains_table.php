<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('cloudflare_zone_id')->nullable();
            $table->enum('status', ['active', 'pending', 'paused', 'moved'])->default('active');
            $table->date('expires_at')->nullable();
            $table->enum('expiry_source', ['whois', 'manual'])->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
