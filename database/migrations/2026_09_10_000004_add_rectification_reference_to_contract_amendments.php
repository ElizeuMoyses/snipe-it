<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contract_amendments', function (Blueprint $table) {
            $table->unsignedInteger('rectifies_amendment_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('contract_amendments', function (Blueprint $table) {
            $table->dropColumn('rectifies_amendment_id');
        });
    }
};
