<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'size' => (string) fake()->randomElement(['1-10', '11-50', '51-200', '201-500', '500+']),
            'referral_source_id' => null,
            'referral_other_text' => null,
            'contract_start' => null,
            'contract_end' => null,
            'sales_person_id' => null,
            'last_contacted' => null,
            'certified_staff' => null,
            'user_id' => null,
        ];
    }
}
