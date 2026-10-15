<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['type' => 'empresa', 'name' => fake()->company()];
    }

    public function agency(): static
    {
        return $this->state(fn (): array => ['type' => 'agencia']);
    }
}
