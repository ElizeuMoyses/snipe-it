<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('supplier_type', 20)->nullable()->after('tag_color');
            $table->string('document', 20)->nullable()->after('supplier_type');
            $table->string('corporate_name', 255)->nullable()->after('document');
            $table->string('internal_code', 50)->nullable()->index()->after('corporate_name');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['internal_code']);
            $table->dropColumn(['supplier_type', 'document', 'corporate_name', 'internal_code']);
        });
    }
};
