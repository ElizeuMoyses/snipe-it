<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('contract_number', 100)->nullable();
            $table->enum('contract_type', ['recurring', 'one_time']);
            $table->unsignedBigInteger('status_label_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semiannual', 'annual', 'one_time'])->nullable();
            $table->decimal('installment_value', 12, 2)->default(0);
            $table->decimal('total_value', 14, 2)->nullable();
            $table->unsignedInteger('total_installments')->nullable();
            $table->string('readjustment_index', 50)->nullable();
            $table->unsignedTinyInteger('readjustment_month')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('status_label_id')->references('id')->on('contract_status_labels');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('supplier_id');
            $table->index('company_id');
            $table->index('created_by');
            $table->index('status_label_id');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
