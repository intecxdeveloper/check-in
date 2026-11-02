<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->index(['company_id', 'id'], 'visitors_company_id_id_index');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->index('visitor_id', 'leads_visitor_id_index');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->index(['public_display_enabled', 'updated_at'], 'companies_public_updated_index');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', fn (Blueprint $table) => $table->dropIndex('visitors_company_id_id_index'));
        Schema::table('leads', fn (Blueprint $table) => $table->dropIndex('leads_visitor_id_index'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropIndex('companies_public_updated_index'));
    }
};
