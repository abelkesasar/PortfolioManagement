<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
Schema::create('assets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('symbol');
    $table->string('name');
    $table->enum('type', ['stock', 'crypto']);
    $table->decimal('current_price', 20, 8)->default(0);
    $table->timestamp('price_updated_at')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'symbol']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};