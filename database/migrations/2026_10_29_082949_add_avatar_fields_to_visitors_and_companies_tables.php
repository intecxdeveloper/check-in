<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->string('avatar_seed', 80)->nullable()->after('display_message');
            $table->string('avatar_path', 255)->nullable()->after('avatar_seed');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('avatar_seed', 80)->nullable()->after('public_website');
            $table->string('avatar_path', 255)->nullable()->after('avatar_seed');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropColumn(['avatar_seed', 'avatar_path']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['avatar_seed', 'avatar_path']);
        });
    }
};
