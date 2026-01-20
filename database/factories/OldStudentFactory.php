<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OldStudent>
 */
class OldStudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $genders = ['male', 'female'];
        $jobs = ['Engineer', 'Doctor', 'Teacher', 'Business Owner', 'Lawyer', 'Accountant', 'Nurse', 'Designer', 'Developer', 'Manager', 'Sales', 'Marketing'];
        
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'dob' => fake()->date('Y-m-d', '2000-01-01'),
            'gender' => fake()->randomElement($genders),
            'nrc_number' => fake()->numerify('##########'),
            'partner_name' => fake()->name(),
            'job' => fake()->randomElement($jobs),
            'photo' => null, // You can add photo generation later if needed
        ];
    }
}
