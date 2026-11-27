<?php

namespace Database\Factories;

use App\Models\Nursery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Branch> */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        $city = fake()->city();

        return [
            'nursery_id' => Nursery::factory(),
            'name' => ['ar' => 'فرع '.$city, 'en' => $city.' branch'],
            'phone' => fake()->phoneNumber(),
        ];
    }
}
