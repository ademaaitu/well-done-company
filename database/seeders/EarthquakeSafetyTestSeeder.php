<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class EarthquakeSafetyTestSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::query()->updateOrCreate(
            ['slug' => 'earthquake-safety-test'],
            [
                'title' => 'Earthquake Safety Test',
                'description' => 'Interactive assessment to practice earthquake preparedness and safety response.',
                'is_active' => true,
            ]
        );

        $scenarios = [
            [
                'question' => 'You feel the first shaking while indoors. What should you do immediately?',
                'options' => [
                    'Run outside as fast as possible',
                    'Drop, Cover, and Hold On under sturdy furniture',
                    'Use the elevator to reach the ground floor',
                    'Stand near windows to observe outside conditions',
                ],
                'correct_option' => 'Drop, Cover, and Hold On under sturdy furniture',
                'explanation' => 'Drop, Cover, and Hold On is the recommended action to reduce injury from falling objects.',
                'sort_order' => 1,
            ],
            [
                'question' => 'You are in bed when an earthquake starts. What is the safest action?',
                'options' => [
                    'Stay in bed and protect your head with a pillow',
                    'Run downstairs immediately',
                    'Go to the balcony',
                    'Stand in a doorway',
                ],
                'correct_option' => 'Stay in bed and protect your head with a pillow',
                'explanation' => 'If already in bed, staying put and protecting your head helps avoid injuries from movement in the dark.',
                'sort_order' => 2,
            ],
            [
                'question' => 'After strong shaking stops, what is your next best step?',
                'options' => [
                    'Light candles to check damage',
                    'Check for injuries, hazards, and prepare for aftershocks',
                    'Turn on all electrical appliances',
                    'Drive immediately even if roads are damaged',
                ],
                'correct_option' => 'Check for injuries, hazards, and prepare for aftershocks',
                'explanation' => 'Post-earthquake safety includes injury checks, hazard inspection, and readiness for aftershocks.',
                'sort_order' => 3,
            ],
        ];

        foreach ($scenarios as $scenario) {
            $module->scenarios()->updateOrCreate(
                ['question' => $scenario['question']],
                $scenario
            );
        }
    }
}