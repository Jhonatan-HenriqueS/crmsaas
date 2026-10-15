<?php

use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register', ['plan' => 'empresa']));

    $response->assertOk();
});

test('new users can register', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.pwnedpasswords.com/range/*' => Http::response('00000:0')]);
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'minha empresa cresce com bons clientes',
        'plan' => 'empresa',
        'terms' => true,
    ]);

    Http::assertSentCount(1);
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
