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

        return [
            'name' => fake()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional(0.9)->unique()->safeEmail(),
            'address' => fake()->optional()->address(),
            'subject' => fake()->optional(0.85)->randomElement($subjects),
            'position' => fake()->optional()->jobTitle(),
            'photo' => null,
            'from_year' => fake()->optional(0.8)->numberBetween(1990, 2020),
            'to_year' => fake()->optional(0.8)->numberBetween(2020, 2025),
        ];
    }
}
