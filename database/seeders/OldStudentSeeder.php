<?php

namespace Database\Seeders;

use App\Models\OldStudent;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OldStudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generate 100 fake old students
        OldStudent::factory()->count(100)->create();
    }
}
