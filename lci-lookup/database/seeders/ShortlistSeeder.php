<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shortlist;

class ShortlistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shortlists = [
            [
                'NID' => 'ABC123',
                'LIC' => 'LIC001',
                'name' => 'John Doe'
            ],
            [
                'NID' => 'DEF456',
                'LIC' => 'LIC002',
                'name' => 'Jane Smith'
            ],
            [
                'NID' => 'GHI789',
                'LIC' => 'LIC003',
                'name' => 'Bob Johnson'
            ],
        ];

        foreach ($shortlists as $shortlist) {
            Shortlist::create($shortlist);
        }
    }
}
