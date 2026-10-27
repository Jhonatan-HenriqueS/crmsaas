<?php

use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['reporting.timezone' => 'America/Porto_Velho']);
    $this->travelTo(CarbonImmutable::parse('2026-10-16 16:00:00', 'UTC'));
});

test('moving a lead changes only category distribution while preserving total and creation metrics', function () {
    $own = Membership::factory()->create();
    $first = LeadCategory::factory()->for($own->account)->create();
    $second = LeadCategory::factory()->for($own->account)->create();
    $leads = Lead::factory()->count(2)->for($own->account)->for($first, 'category')->create(['created_at' => '2026-10-16 12:00:00']);
    Lead::factory()->count(2)->for($own->account)->for($second, 'category')->create(['created_at' => '2026-10-15 12:00:00']);
    $foreign = Lead::factory()->create();
    $before = $foreign->fresh()->getRawOriginal();
    $this->actingAs($own->user);
    $overview = $this->get(route('dashboard'))->assertOk()->inertiaProps('overview');

    $this->patch(route('leads.update', $leads->first()), [
        'name' => 'Nome atualizado', 'category_id' => $second->id, 'first_contact_date' => '2020-01-01',
        'potential_amount' => '9000.50', 'charged_amount' => '500.00',
    ])->assertRedirect();

    $after = $this->get(route('dashboard'))->assertOk()->inertiaProps('overview');
    expect($after['totalLeads'])->toBe(4);
    expect($after['weeklyIntake'])->toBe($overview['weeklyIntake']);
    expect($after['weeklyTotal'])->toBe(4);
    expect($after['inProgressLeads'])->toBe(0);
    expect($after['soldLeads'])->toBe(0);
    $categories = collect($after['categories'])->keyBy('id');
    expect($categories[$first->id]['count'])->toBe(1);
    expect($categories[$first->id]['percentage'])->toBe(25);
    expect($categories[$second->id]['count'])->toBe(3);
    expect($categories[$second->id]['percentage'])->toBe(75);
    $page = $this->get(route('categories.index'))->assertOk()->inertiaProps();
    expect($page['totalLeads'])->toBe(4);
    expect(collect($page['categories']['data'])->keyBy('id')[$first->id]['leads_count'])->toBe(1);
    expect(collect($page['categories']['data'])->keyBy('id')[$second->id]['leads_count'])->toBe(3);
    expect($foreign->fresh()->getRawOriginal())->toBe($before);
});

test('editing a name or contact dates preserves all dashboard metrics', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create(['created_at' => '2026-10-16 04:01:00']);
    $this->actingAs($own->user);
    $before = $this->get(route('dashboard'))->assertOk()->inertiaProps();

    $this->patch(route('leads.update', $lead), ['name' => 'Novo nome', 'description' => 'Descrição nova',
        'category_id' => $lead->category_id, 'first_contact_date' => '2025-01-01', 'next_contact_date' => '2030-01-01'])
        ->assertRedirect();

    $after = $this->get(route('dashboard'))->assertOk()->inertiaProps();
    expect($after['overview'])->toBe($before['overview']);
    expect($after['newLeads'])->toBe($before['newLeads']);
    expect($lead->fresh()->getRawOriginal('created_at'))->toBe('2026-10-16 04:01:00');
});

test('manual deletion recalculates actual totals percentages weekly chart and every period without foreign changes', function () {
    $own = Membership::factory()->create();
    $other = Membership::factory()->create();
    $first = LeadCategory::factory()->for($own->account)->create();
    $second = LeadCategory::factory()->for($own->account)->create();
    $lead = Lead::factory()->for($own->account)->for($first, 'category')->create(['created_at' => '2026-10-16 04:00:00']);
    Lead::factory()->for($own->account)->for($first, 'category')->create(['created_at' => '2026-10-06 12:00:00']);
    Lead::factory()->for($own->account)->for($second, 'category')->create(['created_at' => '2026-09-25 12:00:00']);
    Lead::factory()->for($own->account)->for($second, 'category')->create(['created_at' => '2026-09-01 12:00:00']);
    $foreign = Lead::factory()->for($other->account)->create(['created_at' => '2026-10-16 04:00:00']);
    $foreignBefore = $foreign->fresh()->getRawOriginal();
    $this->actingAs($own->user);
    foreach (['week' => 1, 'fortnight' => 2, 'month' => 3] as $period => $count) {
        $this->get(route('dashboard', ['period' => $period]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('overview.totalLeads', 4)->where('newLeads.count', $count));
    }

    $this->delete(route('leads.destroy', $lead))->assertRedirect();

    foreach (['week' => 0, 'fortnight' => 1, 'month' => 2] as $period => $count) {
        $this->get(route('dashboard', ['period' => $period]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('overview.totalLeads', 3)->where('overview.weeklyTotal', 0)->where('newLeads.count', $count)
            ->where('overview.inProgressLeads', 0)->where('overview.soldLeads', 0));
    }
    $overview = $this->get(route('dashboard'))->assertOk()->inertiaProps('overview');
    expect(array_column($overview['weeklyIntake'], 'count'))->toBe([0, 0, 0, 0, 0, 0, 0]);
    $categories = collect($overview['categories'])->keyBy('id');
    expect($categories[$first->id]['count'])->toBe(1);
    expect($categories[$first->id]['percentage'])->toBe(33.3);
    expect($categories[$second->id]['count'])->toBe(2);
    expect($categories[$second->id]['percentage'])->toBe(66.7);
    $page = $this->get(route('categories.index'))->assertOk()->inertiaProps();
    expect($page['totalLeads'])->toBe(3);
    expect(collect($page['categories']['data'])->keyBy('id')[$first->id]['leads_count'])->toBe(1);
    expect(collect($page['categories']['data'])->keyBy('id')[$first->id]['percentage'])->toBe(33.3);
    $this->assertModelExists($first);
    expect($foreign->fresh()->getRawOriginal())->toBe($foreignBefore);
    $this->actingAs($other->user)->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('overview.totalLeads', 1)->where('newLeads.count', 1));
});
