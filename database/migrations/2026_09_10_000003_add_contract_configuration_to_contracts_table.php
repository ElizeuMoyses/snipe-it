<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_type_id')->nullable()->after('contract_type');
            $table->string('total_value_mode', 20)->default('automatic')->after('total_value');

            $table->foreign('contract_type_id')->references('id')->on('contract_types')->nullOnDelete();
            $table->index('contract_type_id');
        });

        $types = DB::table('contract_types')->pluck('id', 'code');
        foreach (['recurring', 'one_time'] as $code) {
            if ($types->has($code)) {
                DB::table('contracts')
                    ->where('contract_type', $code)
                    ->whereNull('contract_type_id')
                    ->update(['contract_type_id' => $types->get($code)]);
            }
        }

        // Existing rows have no historical mode marker. Keep their previous
        // behavior and values untouched rather than silently reinterpreting
        // them as newly-created automatic totals.
        DB::table('contracts')->update(['total_value_mode' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['contract_type_id']);
            $table->dropIndex(['contract_type_id']);
            $table->dropColumn(['contract_type_id', 'total_value_mode']);
        });
    }
};
