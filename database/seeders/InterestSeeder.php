<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;

class InterestSeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private const INTERESTS = [
        'Professional' => [
            'Technology', 'Entrepreneurship', 'Startups', 'Finance', 'Investing',
            'Real Estate', 'Consulting', 'Nonprofit',
        ],
        'Creative' => [
            'Design', 'Photography', 'Writing', 'Music', 'Film',
        ],
        'Lifestyle' => [
            'Health', 'Fitness', 'Travel', 'Food', 'Sports', 'Sustainability',
        ],
        'Learning' => [
            'Education',
        ],
    ];

    public function run(): void
    {
        foreach (self::INTERESTS as $category => $names) {
            foreach ($names as $name) {
                Interest::updateOrCreate(['name' => $name], ['category' => $category]);
            }
        }
    }
}
