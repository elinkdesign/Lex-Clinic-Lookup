<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Longlist;

class LonglistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $longlists = [
            [
                'NID' => '1234',
                'LIC' => 'LIC004',
                'name' => 'Alice Williams'
            ],
            [
                'NID' => '5678',
                'LIC' => 'LIC005',
                'name' => 'Charlie Brown'
            ],
            [
                'NID' => '9012',
                'LIC' => 'LIC006',
                'name' => 'Diana Clark'
            ],
        ];

        foreach ($longlists as $longlist) {
            Longlist::create($longlist);
        }
    }
}
