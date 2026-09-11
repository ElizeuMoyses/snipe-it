<?php

namespace Tests\Feature\CheckoutAcceptances;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicEulaSchemaTest extends TestCase
{
    public function test_a_fresh_database_supports_public_acceptance_metadata(): void
    {
        $this->assertTrue(Schema::hasColumns('checkout_acceptances', [
            'token', 'token_expires_at', 'failed_attempts', 'blocked_until',
            'signature_latitude', 'signature_longitude', 'signature_device_type', 'signature_ip',
        ]));
        $this->assertTrue(Schema::hasIndex('checkout_acceptances', 'checkout_acceptances_token', 'unique'));
    }

    public function test_reconciliation_can_be_repeated_without_losing_existing_acceptances(): void
    {
        $id = DB::table('checkout_acceptances')->insertGetId([
            'checkoutable_type' => 'App\\Models\\Asset',
            'checkoutable_id' => 123,
            'token' => 'local-schema-regression-check',
            'signature_filename' => 'existing-signature.png',
            'failed_attempts' => 2,
        ]);

        $migration = require database_path('migrations/2026_09_10_000001_reconcile_public_eula_schema.php');
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertDatabaseHas('checkout_acceptances', [
            'id' => $id,
            'token' => 'local-schema-regression-check',
            'signature_filename' => 'existing-signature.png',
            'failed_attempts' => 2,
        ]);
    }
}
