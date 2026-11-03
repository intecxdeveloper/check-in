<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->string('other_interest')->nullable()->after('display_message');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('other_interest')->nullable()->after('public_website');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropColumn('other_interest');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('other_interest');
        });
    }
};
