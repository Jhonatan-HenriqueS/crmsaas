<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Membership> */
class MembershipFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'account_id' => Account::factory(), 'role' => 'owner'];
    }
}
