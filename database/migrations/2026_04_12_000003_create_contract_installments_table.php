<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedInteger('installment_number');
            $table->date('reference_date');
            $table->date('due_date');
            $table->decimal('expected_value', 12, 2);
            $table->decimal('paid_value', 12, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->unsignedBigInteger('status_label_id');
            $table->string('ticket_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('status_label_id')->references('id')->on('contract_status_labels');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('contract_id');
            $table->index('due_date');
            $table->index('status_label_id');
            $table->index(['contract_id', 'status_label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_installments');
    }
};
