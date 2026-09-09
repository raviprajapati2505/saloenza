<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Hair', 'Skin', 'Nails', 'Grooming', 'Spa'] as $name) {
            Category::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }
    }
}
