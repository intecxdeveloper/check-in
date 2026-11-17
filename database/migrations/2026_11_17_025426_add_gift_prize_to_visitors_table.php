<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->string('gift_prize', 40)->nullable()->after('display_message_removed_at');
            $table->timestamp('gift_spun_at')->nullable()->after('gift_prize');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropColumn(['gift_prize', 'gift_spun_at']);
        });
    }
};
