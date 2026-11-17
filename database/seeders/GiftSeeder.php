<?php

namespace Database\Seeders;

use App\Models\Gift;
use Illuminate\Database\Seeder;

class GiftSeeder extends Seeder
{
    public function run(): void
    {
        Gift::loadStarterPrizes();
    }
}
