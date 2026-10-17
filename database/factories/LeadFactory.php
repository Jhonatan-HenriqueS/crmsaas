<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Lead;
use App\Models\LeadCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'category_id' => fn (array $attributes): int => LeadCategory::factory()->create(['account_id' => $attributes['account_id']])->id,
            'name' => fake()->company(),
            'description' => null,
            'first_contact_date' => fake()->date('Y-m-d'),
            'next_contact_date' => null,
            'potential_amount' => null,
            'charged_amount' => null,
        ];
    }
}
