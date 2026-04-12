<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_status_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('scope', ['contract', 'installment']);
            $table->string('meta_type', 30);
            $table->string('color', 10)->nullable();
            $table->string('icon', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('scope');
            $table->index('meta_type');
            $table->index(['scope', 'meta_type']);
            $table->index('is_default');
        });

        // Seed default status labels
        $now = now();

        DB::table('contract_status_labels')->insert([
            // Installment statuses
            ['name' => 'Pendente',   'scope' => 'installment', 'meta_type' => 'pending',   'color' => '#FF9800', 'icon' => 'fa-clock',                 'sort_order' => 1, 'is_default' => true, 'notes' => null, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['name' => 'Pago',       'scope' => 'installment', 'meta_type' => 'paid',      'color' => '#4CAF50', 'icon' => 'fa-check-circle',           'sort_order' => 2, 'is_default' => true, 'notes' => null, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['name' => 'Em Atraso',  'scope' => 'installment', 'meta_type' => 'overdue',   'color' => '#F44336', 'icon' => 'fa-exclamation-triangle',   'sort_order' => 3, 'is_default' => true, 'notes' => null, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['name' => 'Cancelado',  'scope' => 'installment', 'meta_type' => 'cancelled', 'color' => '#9E9E9E', 'icon' => 'fa-ban',                    'sort_order' => 4, 'is_default' => true, 'notes' => null, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],

            // Contract statuses
            ['name' => 'Rascunho',   'scope' => 'contract', 'meta_type' => 'draft',     'color' => '#9E9E9E', 'icon' => 'fa-pencil',           'sort_order' => 1, 'is_default' => true, 'notes' => null, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['name' => 'Ativo',      'scope' => 'contract', 'meta_type' => 'active',    'color' => '#4CAF50', 'icon' => 'fa-check-circle',     'sort_order' => 2, 'is_default' => true, 'notes' => null, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['name' => 'Suspenso',   'scope' => 'contract', 'meta_type' => 'suspended', 'color' => '#FF9800', 'icon' => 'fa-pause-circle',     'sort_order' => 3, 'is_default' => true, 'notes' => null, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['name' => 'Cancelado',  'scope' => 'contract', 'meta_type' => 'cancelled', 'color' => '#F44336', 'icon' => 'fa-ban',               'sort_order' => 4, 'is_default' => true, 'notes' => null, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['name' => 'Expirado',   'scope' => 'contract', 'meta_type' => 'expired',   'color' => '#795548', 'icon' => 'fa-calendar-times-o', 'sort_order' => 5, 'is_default' => true, 'notes' => null, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_status_labels');
    }
};
