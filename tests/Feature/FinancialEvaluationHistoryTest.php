<?php

namespace Tests\Feature;

use App\Models\FinancialEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialEvaluationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_paginated_and_isolated_by_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        FinancialEvaluation::factory()->for($user)->create([
            'evaluation_date' => '2026-09-02',
            'view' => 'current',
            'result' => ['situation' => 'under_control', 'percentage' => '20.00'],
        ]);
        FinancialEvaluation::factory()->for($other)->create([
            'evaluation_date' => '2026-09-02',
            'view' => 'current',
            'result' => ['situation' => 'outside_plan', 'percentage' => '120.00'],
        ]);

        $this->actingAs($user)->get(route('financial-evaluations.index', [
            'view' => 'current', 'from' => '2026-09-01', 'to' => '2026-09-03',
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('FinancialEvaluations/Index')
            ->where('filters.view', 'current')
            ->where('evaluations.data.0.result.situation', 'under_control')
            ->has('evaluations.data', 1));
    }

    public function test_history_shows_an_empty_period_without_leaking_other_views(): void
    {
        $user = User::factory()->create();
        FinancialEvaluation::factory()->for($user)->create(['view' => 'projected', 'evaluation_date' => '2026-09-02']);

        $this->actingAs($user)->get(route('financial-evaluations.index', [
            'view' => 'current', 'from' => '2026-09-01', 'to' => '2026-09-03',
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('FinancialEvaluations/Index')
            ->has('evaluations.data', 0));
    }
}
