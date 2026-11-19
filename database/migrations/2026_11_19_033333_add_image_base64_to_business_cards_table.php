<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_cards', function (Blueprint $table) {
            $table->longText('image_base64')->nullable()->after('image_path');
            $table->string('image_mime', 32)->nullable()->after('image_base64');
            $table->string('image_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('business_cards', function (Blueprint $table) {
            $table->dropColumn(['image_base64', 'image_mime']);
        });
    }
};
