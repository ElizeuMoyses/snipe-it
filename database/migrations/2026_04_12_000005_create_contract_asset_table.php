<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_asset', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('asset_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['contract_id', 'asset_id']);
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('asset_id')->references('id')->on('assets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_asset');
    }
};
