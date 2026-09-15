<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
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

    public function test_card_charge_migration_recovers_after_a_partial_mysql_application(): void
    {
        Schema::drop('card_charge_payment_allocations');

        $migration = require database_path(
            'migrations/2026_09_12_000001_create_card_charge_domain_tables.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasTable('card_charge_payment_allocations'));
        $this->assertTrue(Schema::hasColumn('card_payments', 'selected_charge_ids'));
        $this->assertTrue(Schema::hasIndex(
            'card_charge_payment_allocations',
            'card_payment_charge_unique',
            'unique'
        ));
    }

    public function test_financial_evaluation_migration_recovers_when_the_final_index_is_missing(): void
    {
        Schema::table('financial_evaluations', function (Blueprint $table) {
            $table->dropIndex('financial_evaluations_scope_index');
        });

        $migration = require database_path(
            'migrations/2026_09_12_024716_add_revision_to_financial_evaluations_table.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasIndex(
            'financial_evaluations',
            'financial_evaluations_scope_index'
        ));
    }

    public function test_domain_index_names_respect_the_mysql_identifier_limit(): void
    {
        $tables = [
            'card_payment_allocations',
            'card_charge_payment_allocations',
            'card_advance_allocations',
            'financial_evaluations',
        ];

        foreach ($tables as $table) {
            foreach (Schema::getIndexListing($table) as $indexName) {
                $this->assertLessThanOrEqual(64, strlen($indexName), $indexName);
            }
        }
    }
}
