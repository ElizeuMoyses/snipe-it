<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_audit_events', function (Blueprint $table) {
            $table->id();
            // Deliberately no FK: a future physical purge must not erase the audit trail.
            $table->unsignedBigInteger('contract_id');
            $table->unsignedInteger('company_id')->nullable();
            $table->string('auditable_type', 191);
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('action', 80);
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('actor_type', 20)->default('system');
            $table->string('source', 20)->default('system');
            $table->uuid('correlation_id')->nullable();
            $table->string('idempotency_key', 191)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['contract_id', 'occurred_at', 'id']);
            $table->index(['contract_id', 'action']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('actor_id');
            $table->unique(['contract_id', 'idempotency_key'], 'contract_audit_events_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_audit_events');
    }
};
