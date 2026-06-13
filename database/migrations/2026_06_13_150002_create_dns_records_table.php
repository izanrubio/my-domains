<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('cloudflare_record_id');
            $table->string('type');
            $table->string('name');
            $table->text('content');
            $table->integer('ttl')->default(1);
            $table->boolean('proxied')->default(false);
            $table->timestamps();

            $table->unique(['domain_id', 'cloudflare_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};
