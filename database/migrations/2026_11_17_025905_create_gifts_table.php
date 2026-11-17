<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('label', 80);
            $table->string('short', 16);
            $table->string('color', 7);
            $table->unsignedTinyInteger('weight')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gifts');
    }
};
