<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subjects = ['Mathematics', 'English', 'Science', 'History', 'Geography', 'Physics', 'Chemistry', 'Biology', 'Myanmar', 'Art', 'Music', 'Physical Education'];
        $genders = ['male', 'female'];
        
        return [
            'name' => fake()->name(),
            'gender' => fake()->randomElement($genders),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'subject' => fake()->randomElement($subjects),
            'photo' => null, // You can add photo generation later if needed
            'from_year' => fake()->numberBetween(1990, 2020),
            'to_year' => fake()->numberBetween(2020, 2025),
        ];
    }
}
