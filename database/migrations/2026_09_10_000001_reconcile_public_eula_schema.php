<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adopt the public acceptance schema already present in production.
     * Existing columns and their data must not be replaced.
     */
    public function up(): void
    {
        $columns = Schema::getColumnListing('checkout_acceptances');

        Schema::table('checkout_acceptances', function (Blueprint $table) use ($columns) {
            if (! in_array('token', $columns, true)) {
                $table->string('token', 255)->nullable();
            }
            if (! in_array('token_expires_at', $columns, true)) {
                $table->timestamp('token_expires_at')->nullable();
            }
            if (! in_array('failed_attempts', $columns, true)) {
                $table->integer('failed_attempts')->default(0);
            }
            if (! in_array('blocked_until', $columns, true)) {
                $table->timestamp('blocked_until')->nullable();
            }
            if (! in_array('signature_latitude', $columns, true)) {
                $table->decimal('signature_latitude', 10, 8)->nullable();
            }
            if (! in_array('signature_longitude', $columns, true)) {
                $table->decimal('signature_longitude', 11, 8)->nullable();
            }
            if (! in_array('signature_device_type', $columns, true)) {
                $table->string('signature_device_type', 50)->nullable();
            }
            if (! in_array('signature_ip', $columns, true)) {
                $table->string('signature_ip', 45)->nullable();
            }
        });

        $indexes = [
            'checkout_acceptances_token' => ['token'],
            'idx_completed_signatures' => ['accepted_at', 'signature_filename'],
            'idx_device_type' => ['signature_device_type'],
            'idx_geolocation' => ['signature_latitude', 'signature_longitude'],
        ];

        foreach ($indexes as $name => $fields) {
            if (! Schema::hasIndex('checkout_acceptances', $name)) {
                Schema::table('checkout_acceptances', function (Blueprint $table) use ($name, $fields) {
                    if ($name === 'checkout_acceptances_token') {
                        $table->unique($fields, $name);
                    } else {
                        $table->index($fields, $name);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: these columns may predate this migration
        // and contain existing signatures, tokens and acceptance metadata.
    }
};
