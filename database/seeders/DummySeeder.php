<?php

namespace Database\Seeders;

use App\Models\Dummy;
use Illuminate\Database\Seeder;

class DummySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Dummy::factory()->count(23)->create();
    }
}
