<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private const SKILLS = [
        'Engineering' => [
            'Laravel', 'PHP', 'React', 'React Native', 'Flutter', 'Node.js', 'Python',
            'Go', 'Java', 'Kotlin', 'Swift', 'TypeScript', 'Ruby on Rails',
        ],
        'Data' => [
            'Data Science', 'Machine Learning', 'Data Engineering', 'SQL', 'Analytics',
        ],
        'Infrastructure' => [
            'DevOps', 'Kubernetes', 'AWS', 'Cybersecurity',
        ],
        'Design' => [
            'UI/UX Design', 'Product Design', 'Graphic Design', 'Motion Design',
        ],
        'Business' => [
            'Product Management', 'Project Management', 'Sales', 'Marketing',
            'Growth', 'Business Development', 'Finance', 'Copywriting',
        ],
    ];

    public function run(): void
    {
        foreach (self::SKILLS as $category => $names) {
            foreach ($names as $name) {
                Skill::updateOrCreate(['name' => $name], ['category' => $category]);
            }
        }
    }
}
