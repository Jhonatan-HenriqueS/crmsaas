<?php

use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\Membership;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Http::preventStrayRequests());

/** @return array<string, mixed> */
function leadMapsPayload(Membership $membership, ?string $mapsUrl): array
{
    return ['name' => 'Empresa Mapa', 'category_id' => LeadCategory::factory()->for($membership->account)->create()->id,
        'first_contact_date' => '2026-10-01', 'maps_url' => $mapsUrl];
}

test('long links store the coordinates found in the url', function (string $url, string $latitude, string $longitude) {
    $own = Membership::factory()->create();

    $this->actingAs($own->user)->post(route('leads.store'), leadMapsPayload($own, $url))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('leads', ['account_id' => $own->account_id, 'maps_url' => $url, 'latitude' => $latitude, 'longitude' => $longitude]);
    $this->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page
        ->where('leads.data.0.maps_url', $url)->where('leads.data.0.latitude', $latitude)->where('leads.data.0.longitude', $longitude));
})->with([
    'pin' => ['https://www.google.com/maps/place/Ji-Paran%C3%A1/@-10.8,-61.9,15z/data=!3d-10.8777000!4d-61.9512000', '-10.8777000', '-61.9512000'],
    'viewport' => ['https://www.google.com.br/maps/@-23.5505199,-46.6333094,17z', '-23.5505199', '-46.6333094'],
    'query' => ['https://maps.google.com/?q=-8.76,-63.9', '-8.7600000', '-63.9000000'],
    'll' => ['https://maps.google.com/maps?ll=40.7128,-74.006&z=12', '40.7128000', '-74.0060000'],
    'dropped pin' => ['https://www.google.com/maps/search/-10.882253,+-61.967565?entry=tts', '-10.8822530', '-61.9675650'],
]);

test('app share links without coordinates are geocoded from the place address', function () {
    Sleep::fake();
    Http::fake([
        'https://maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/Devonwood+Manor+Motel+312+Fenton+Street,+Glenholme,+Rotorua+3010,+New+Zealand/data=!4m2!3m1!1s0x6d6c27607e543dd5:0x94325847b386a021?entry=gps&g_st=ic']),
        'https://nominatim.openstreetmap.org/*' => Http::sequence()->push([])->push([['lat' => '-38.1516492', 'lon' => '176.2541514']]),
    ]);
    $own = Membership::factory()->create();
    $shared = "Devonwood Manor Motel\n312 Fenton Street, Rotorua\nhttps://maps.app.goo.gl/H2A6kBf6BA5TL95YA?g_st=ic";

    $this->actingAs($own->user)->post(route('leads.store'), leadMapsPayload($own, $shared))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('leads', ['maps_url' => 'https://maps.app.goo.gl/H2A6kBf6BA5TL95YA?g_st=ic', 'latitude' => '-38.1516492', 'longitude' => '176.2541514']);
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/search')
        && $request['q'] === '312 Fenton Street, Glenholme, Rotorua 3010, New Zealand' && $request->hasHeader('User-Agent', config('app.name').' ('.config('app.url').')'));
    Sleep::assertSleptTimes(1);
});

test('short links are resolved on the server through allowlisted redirects only', function () {
    Http::fake(['https://maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/@-10.88,-61.95,15z'])]);
    $own = Membership::factory()->create();

    $this->actingAs($own->user)->post(route('leads.store'), leadMapsPayload($own, 'https://maps.app.goo.gl/abc123'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('leads', ['latitude' => '-10.8800000', 'longitude' => '-61.9500000']);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://maps.app.goo.gl/abc123');
});

test('links without a resolvable position are rejected with a friendly message', function (string $url, ?string $location) {
    Http::fake(['*' => Http::response('', 302, array_filter(['Location' => $location]))]);
    $own = Membership::factory()->create();

    $this->actingAs($own->user)->post(route('leads.store'), leadMapsPayload($own, $url))
        ->assertSessionHasErrors(['maps_url' => 'Não foi possível localizar a posição. Abra o local no Google Maps, toque em Compartilhar e cole o link copiado.']);

    expect(Lead::query()->count())->toBe(0);
})->with([
    'no coordinates' => ['https://www.google.com/maps/place/Ji-Paran%C3%A1', null],
    'short link without location' => ['https://maps.app.goo.gl/empty', null],
    'redirect outside the allowlist' => ['https://maps.app.goo.gl/evil', 'https://evil.example/@1,2'],
]);

test('links outside the google maps hosts are rejected without any request', function (string $url) {
    $own = Membership::factory()->create();

    $this->actingAs($own->user)->post(route('leads.store'), leadMapsPayload($own, $url))->assertSessionHasErrors('maps_url');

    Http::assertNothingSent();
})->with(['https://evil.example/maps/@1,2', 'https://maps.google.com.evil.example/@1,2', 'https://maps.google.com@evil.example/@1,2', 'javascript:alert(1)']);

test('editing clears the location and never accepts coordinates from the browser', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create(['maps_url' => 'https://maps.google.com/?q=1,2', 'latitude' => '1', 'longitude' => '2']);
    $payload = [...$lead->only(['name', 'category_id']), 'first_contact_date' => '2026-10-01'];

    $this->actingAs($own->user)->patch(route('leads.update', $lead), [...$payload, 'maps_url' => '', 'latitude' => '5'])->assertSessionHasErrors('form');
    $this->patch(route('leads.update', $lead), [...$payload, 'maps_url' => ''])->assertSessionHasNoErrors();

    expect($lead->fresh()->only(['maps_url', 'latitude', 'longitude']))->toBe(['maps_url' => null, 'latitude' => null, 'longitude' => null]);
});

test('editing other fields keeps a saved short link without resolving it again', function () {
    $own = Membership::factory()->create();
    $lead = Lead::factory()->for($own->account)->create(['maps_url' => 'https://maps.app.goo.gl/abc', 'latitude' => '-10.5', 'longitude' => '-61.5']);

    $this->actingAs($own->user)->patch(route('leads.update', $lead), [...$lead->only(['category_id', 'maps_url']), 'name' => 'Novo nome', 'first_contact_date' => '2026-10-01'])
        ->assertSessionHasNoErrors();

    expect($lead->fresh()->only(['name', 'latitude', 'longitude']))->toBe(['name' => 'Novo nome', 'latitude' => '-10.5000000', 'longitude' => '-61.5000000']);
    Http::assertNothingSent();
});
