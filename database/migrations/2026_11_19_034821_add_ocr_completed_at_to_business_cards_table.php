<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_cards', function (Blueprint $table) {
            $table->timestamp('ocr_completed_at')->nullable()->after('ocr_text');
        });

        DB::table('business_cards')
            ->where(fn ($query) => $query->whereNotNull('ocr_text')->orWhereNotNull('full_name'))
            ->update(['ocr_completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('business_cards', function (Blueprint $table) {
            $table->dropColumn('ocr_completed_at');
        });
    }
};
