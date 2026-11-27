<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\Nursery> */
class NurseryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => ['ar' => 'حضانة '.$name, 'en' => $name.' Nursery'],
            'slug' => Str::slug($name).'-'.Str::random(4),
            'default_locale' => 'ar',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }
}
