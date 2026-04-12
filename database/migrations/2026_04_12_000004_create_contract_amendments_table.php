<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_amendments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->enum('amendment_type', ['readjustment', 'scope_change', 'renewal', 'termination']);
            $table->text('description');
            $table->decimal('old_value', 12, 2)->nullable();
            $table->decimal('new_value', 12, 2)->nullable();
            $table->date('old_end_date')->nullable();
            $table->date('new_end_date')->nullable();
            $table->date('effective_date');
            $table->string('ticket_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('contract_id');
            $table->index('amendment_type');
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_amendments');
    }
};
