<?php

use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use Inertia\Testing\AssertableInertia as Assert;

test('completion reopening customization and deletion update only the authorized account metrics', function () {
    $own = Membership::factory()->create();
    $protected = $own->account->leadCategories()->sole();
    $ordinary = LeadCategory::factory()->for($own->account)->create(['name' => 'Vendido', 'color' => '#123456']);
    $leads = Lead::factory()->count(4)->for($own->account)->for($ordinary, 'category')->create(['charged_amount' => '100.00']);
    $foreign = Membership::factory()->create();
    Lead::factory()->count(3)->for($foreign->account)->for($foreign->account->leadCategories()->sole(), 'category')->create();
    $this->actingAs($own->user);
    $initial = $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.soldLeads', 0)
        ->where('overview.soldPercentage', 0)->where('overview.inProgressLeads', 0))->inertiaProps();
    $lead = $leads->first();
    $this->post(route('leads.complete', $lead), ['confirm_completion' => true])->assertRedirect();
    $after = $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 4)
        ->where('overview.soldLeads', 1)->where('overview.soldPercentage', 25)->where('overview.inProgressLeads', 0))->inertiaProps();
    expect($after['overview']['weeklyIntake'])->toBe($initial['overview']['weeklyIntake']);
    expect($after['newLeads'])->toBe($initial['newLeads']);
    $this->get(route('categories.index'))->assertInertia(fn (Assert $page) => $page->where('programmedCategories.0.leads_count', 1)
        ->where('programmedCategories.0.percentage', 25)->where('categories.data.0.leads_count', 3)->where('categories.data.0.percentage', 75));
    $this->patch(route('categories.update', $protected), ['name' => 'Entrega aceita', 'color' => '#fedcba'])->assertRedirect();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.soldLeads', 1)
        ->where('overview.categories.1.name', 'Entrega aceita')->where('overview.categories.1.color', '#fedcba')
        ->where('overview.categories.1.count', 1)->where('overview.categories.1.percentage', 25));
    $this->patch(route('leads.update', $lead), ['name' => $lead->name, 'first_contact_date' => $lead->first_contact_date->toDateString(), 'category_id' => $ordinary->id, 'confirm_reopening' => true])->assertRedirect();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 4)
        ->where('overview.soldLeads', 0)->where('overview.soldPercentage', 0));
    $this->post(route('leads.complete', $lead), ['confirm_completion' => true])->assertRedirect();
    $this->delete(route('leads.destroy', $lead))->assertRedirect();
    $this->assertModelExists($protected);
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 3)->where('overview.soldLeads', 0));
    $this->actingAs($foreign->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.soldLeads', 3)->where('overview.soldPercentage', 100));
});

test('an account without leads has its permanent category and finite zero sales percentages', function () {
    $own = Membership::factory()->create();
    $this->actingAs($own->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.totalLeads', 0)
        ->where('overview.soldLeads', 0)->where('overview.soldPercentage', 0)->where('overview.soldRevenue', '0.00')->where('overview.categories.0.count', 0)
        ->where('overview.categories.0.percentage', 0));
});

test('sold revenue sums only the charged amounts of completed leads in the current account', function () {
    $own = Membership::factory()->create();
    $completed = $own->account->leadCategories()->sole();
    $ordinary = LeadCategory::factory()->for($own->account)->create();
    $amounts = ['0.01', '0.99', '10.10', '99.99', '150.50', '1000.00', '1234.56', '2500.05', '9999.99', '1234567.89'];
    foreach ($amounts as $amount) {
        Lead::factory()->for($own->account)->for($completed, 'category')->create(['charged_amount' => $amount]);
    }
    Lead::factory()->for($own->account)->for($completed, 'category')->create();
    $reopened = Lead::factory()->for($own->account)->for($completed, 'category')->create(['charged_amount' => '500.00']);
    Lead::factory()->for($own->account)->for($ordinary, 'category')->create(['charged_amount' => '777.77']);
    $foreign = Membership::factory()->create();
    Lead::factory()->for($foreign->account)->for($foreign->account->leadCategories()->sole(), 'category')->create(['charged_amount' => '888.88']);
    $this->actingAs($own->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.soldRevenue', '1250064.08')
        ->where('overview.soldLeads', 12)->where('overview.totalLeads', 13)->where('overview.soldPercentage', 92.3));
    $this->patch(route('leads.update', $reopened), ['name' => $reopened->name, 'first_contact_date' => $reopened->first_contact_date->toDateString(),
        'category_id' => $ordinary->id, 'charged_amount' => '500.00', 'confirm_reopening' => true])->assertRedirect();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.soldRevenue', '1249564.08')->where('overview.soldLeads', 11));
    $this->actingAs($foreign->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('overview.soldRevenue', '888.88'));
});
