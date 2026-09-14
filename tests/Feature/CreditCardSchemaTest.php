<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreditCardSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_card_migration_recovers_after_a_partial_mysql_application(): void
    {
        Schema::drop('card_payment_allocations');

        $migration = require database_path(
            'migrations/2026_09_09_093501_create_credit_card_domain_tables.php'
        );

        $migration->up();

        $index = collect(Schema::getIndexes('card_payment_allocations'))
            ->firstWhere('name', 'card_payment_installment_unique');

        $this->assertTrue(Schema::hasTable('card_payment_allocations'));
        $this->assertNotNull($index);
        $this->assertSame(['card_payment_id', 'card_installment_id'], $index['columns']);
        $this->assertTrue($index['unique']);
        $this->assertLessThanOrEqual(64, strlen($index['name']));
    }
}
