<?php

namespace Database\Seeders;

use App\Models\Slot;
use Illuminate\Database\Seeder;

class SlotSeeder extends Seeder
{
    public function run(): void
    {
        $slots = [
            ['capacity' => 10],
            ['capacity' => 25],
            ['capacity' => 50],
            ['capacity' => 1],
        ];

        foreach ($slots as $slotData) {
            $capacity = $slotData['capacity'];

            Slot::query()->create([
                'capacity' => $capacity,
                'remaining' => $capacity - 1,
            ]);
        }
    }
}
