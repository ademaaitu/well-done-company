<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class EarthquakeSafetyTestSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'legacy_names' => ['Home Safety', 'Безопасность дома'],
                'name' => 'Безопасность дома',
                'description' => 'Действия при землетрясении дома: мебель, координация с семьёй и первичные риски.',
            ],
            [
                'legacy_names' => ['Mall Safety', 'Безопасность в ТЦ'],
                'name' => 'Безопасность в ТЦ',
                'description' => 'Действия в людной коммерческой среде: паника, маршруты эвакуации и контроль поведения.',
            ],
            [
                'legacy_names' => ['Transport Safety', 'Безопасность в транспорте'],
                'name' => 'Безопасность в транспорте',
                'description' => 'Реакция на землетрясение в движущемся транспорте и на транспортной инфраструктуре.',
            ],
        ];

        foreach ($modules as $moduleData) {
            $module = Module::query()
                ->whereIn('name', $moduleData['legacy_names'])
                ->orderBy('id')
                ->first();

            if ($module) {
                $module->update([
                    'name' => $moduleData['name'],
                    'description' => $moduleData['description'],
                ]);
            } else {
                $module = Module::query()->create([
                    'name' => $moduleData['name'],
                    'description' => $moduleData['description'],
                ]);
            }

            $this->seedModuleScenarios($module->id);
        }
    }

    private function seedModuleScenarios(int $moduleId): void
    {
        $baseScenarios = [
            [
                'branching_id' => 'start',
                'stress_context' => null,
                'sort_order' => 1,
                'question' => 'Вы ощущаете сильную тряску. Что нужно сделать в первую очередь?',
                'options' => [
                    'A' => 'Сразу бежать к лестнице',
                    'B' => 'Пригнуться, укрыться и держаться',
                    'C' => 'Воспользоваться лифтом для быстрого выхода',
                ],
                'correct_answer' => 'B',
                'wrong_explanation' => 'Во время активной тряски движение к лестницам и лифтам повышает риск травм.',
                'next_branching_map' => [
                    'A' => 'stress_1',
                    'B' => 'stress_1',
                    'C' => 'stress_1',
                ],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 2, 'B' => 10, 'C' => 0],
                    'risk_by_option' => ['A' => 75, 'B' => 20, 'C' => 90],
                    'hint_by_option' => [
                        'A' => 'Избегайте лестниц во время сильной тряски.',
                        'C' => 'Лифты могут перестать работать во время землетрясения.',
                    ],
                    'checklist_by_option' => [
                        'A' => 'Тренируйте «Пригнуться, укрыться и держаться» в местах, где бываете каждый день.',
                        'C' => 'Пользуйтесь лестницей только после окончания тряски и подтверждения безопасности.',
                    ],
                    'next_branching_by_option' => [
                        'A' => 'stress_1',
                        'B' => 'stress_1',
                        'C' => 'stress_1',
                    ],
                ],
            ],
            [
                'branching_id' => 'stress_1',
                'stress_context' => 'bus',
                'sort_order' => 2,
                'question' => 'Стресс-сценарий (автобус): пассажиры паникуют. Ваше лучшее действие?',
                'options' => [
                    'A' => 'Проталкиваться через толпу к передней двери',
                    'B' => 'Держаться за опору и выполнять указания водителя',
                    'C' => 'Немедленно выпрыгнуть',
                ],
                'correct_answer' => 'B',
                'wrong_explanation' => 'Резкие движения в движущемся или неустойчивом транспорте приводят к падениям и травмам.',
                'next_branching_map' => ['A' => 'stress_2', 'B' => 'stress_2', 'C' => 'stress_2'],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 3, 'B' => 10, 'C' => 0],
                    'risk_by_option' => ['A' => 70, 'B' => 25, 'C' => 95],
                    'checklist_by_option' => ['C' => 'Никогда не выпрыгивайте из движущегося или неустойчивого транспорта во время тряски.'],
                    'next_branching_by_option' => ['A' => 'stress_2', 'B' => 'stress_2', 'C' => 'stress_2'],
                ],
            ],
            [
                'branching_id' => 'stress_2',
                'stress_context' => 'bus',
                'sort_order' => 3,
                'question' => 'Стресс-сценарий (автобус): маршрут перекрыт обломками. Что делать?',
                'options' => [
                    'A' => 'Ждать подтверждённых указаний по изменению маршрута',
                    'B' => 'Идти через закрытую зону',
                    'C' => 'Встать под эстакадой для укрытия',
                ],
                'correct_answer' => 'A',
                'wrong_explanation' => 'Закрытые зоны и участки под нависающими конструкциями особенно опасны после сильной тряски.',
                'next_branching_map' => ['A' => 'stress_3', 'B' => 'stress_3', 'C' => 'stress_3'],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 10, 'B' => 2, 'C' => 1],
                    'risk_by_option' => ['A' => 20, 'B' => 82, 'C' => 88],
                    'next_branching_by_option' => ['A' => 'stress_3', 'B' => 'stress_3', 'C' => 'stress_3'],
                ],
            ],
            [
                'branching_id' => 'stress_3',
                'stress_context' => 'bus',
                'sort_order' => 4,
                'question' => 'Стресс-сценарий (автобус): связь нестабильна. Лучшая стратегия коммуникации?',
                'options' => [
                    'A' => 'Отправлять короткие сообщения и экономить батарею',
                    'B' => 'Начать длительные видеозвонки',
                    'C' => 'Игнорировать связь до конца эвакуации',
                ],
                'correct_answer' => 'A',
                'wrong_explanation' => 'При перегрузке сети короткие текстовые сообщения надёжнее тяжёлых звонков.',
                'next_branching_map' => ['A' => 'final_test', 'B' => 'final_test', 'C' => 'final_test'],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 10, 'B' => 3, 'C' => 2],
                    'risk_by_option' => ['A' => 22, 'B' => 65, 'C' => 55],
                    'next_branching_by_option' => ['A' => 'final_test', 'B' => 'final_test', 'C' => 'final_test'],
                ],
            ],
            [
                'branching_id' => 'stress_1',
                'stress_context' => 'mall',
                'sort_order' => 2,
                'question' => 'Стресс-сценарий (ТЦ): толпа начинает бежать. Лучшая реакция?',
                'options' => [
                    'A' => 'Двигаться вместе с потоком, защищая голову',
                    'B' => 'Расталкивать людей, чтобы первым попасть к эскалатору',
                    'C' => 'Остаться рядом со стеклянной витриной',
                ],
                'correct_answer' => 'A',
                'wrong_explanation' => 'Заторы у эскалаторов и зоны рядом со стеклом значительно повышают риск травм.',
                'next_branching_map' => ['A' => 'stress_2', 'B' => 'stress_2', 'C' => 'stress_2'],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 10, 'B' => 2, 'C' => 1],
                    'risk_by_option' => ['A' => 28, 'B' => 85, 'C' => 80],
                    'next_branching_by_option' => ['A' => 'stress_2', 'B' => 'stress_2', 'C' => 'stress_2'],
                ],
            ],
            [
                'branching_id' => 'stress_2',
                'stress_context' => 'mall',
                'sort_order' => 3,
                'question' => 'Стресс-сценарий (ТЦ): указатели выхода противоречат друг другу. Чему следовать?',
                'options' => [
                    'A' => 'Ближайшему аварийному маршруту с указаниями персонала',
                    'B' => 'Кратчайшему пути через повреждённый коридор',
                    'C' => 'К лифтовому холлу',
                ],
                'correct_answer' => 'A',
                'wrong_explanation' => 'Повреждённые коридоры и лифты небезопасны во время эвакуации после землетрясения.',
                'next_branching_map' => ['A' => 'stress_3', 'B' => 'stress_3', 'C' => 'stress_3'],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 10, 'B' => 2, 'C' => 0],
                    'risk_by_option' => ['A' => 25, 'B' => 78, 'C' => 92],
                    'next_branching_by_option' => ['A' => 'stress_3', 'B' => 'stress_3', 'C' => 'stress_3'],
                ],
            ],
            [
                'branching_id' => 'stress_3',
                'stress_context' => 'mall',
                'sort_order' => 4,
                'question' => 'Стресс-сценарий (ТЦ): рядом есть пострадавший. Что делать?',
                'options' => [
                    'A' => 'Переместиться в безопасную зону и сообщить экстренным службам',
                    'B' => 'Тащить человека через толпу без оценки состояния',
                    'C' => 'Сразу уйти и никому не сообщать',
                ],
                'correct_answer' => 'A',
                'wrong_explanation' => 'Небезопасное перемещение может усугубить травмы и замедлить помощь.',
                'next_branching_map' => ['A' => 'final_test', 'B' => 'final_test', 'C' => 'final_test'],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 10, 'B' => 3, 'C' => 1],
                    'risk_by_option' => ['A' => 30, 'B' => 72, 'C' => 68],
                    'next_branching_by_option' => ['A' => 'final_test', 'B' => 'final_test', 'C' => 'final_test'],
                ],
            ],
            [
                'branching_id' => 'stress_1',
                'stress_context' => 'office',
                'sort_order' => 2,
                'question' => 'Стресс-сценарий (офис): стеллажи сильно качаются. Лучшая реакция?',
                'options' => [
                    'A' => 'Отойти от стеллажей и защитить шею/голову',
                    'B' => 'Держать стеллаж, чтобы предметы не падали',
                    'C' => 'Бежать под подвесные светильники',
                ],
                'correct_answer' => 'A',
                'wrong_explanation' => 'Попытка удерживать тяжёлую мебель может привести к серьёзным травмам.',
                'next_branching_map' => ['A' => 'stress_2', 'B' => 'stress_2', 'C' => 'stress_2'],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 10, 'B' => 2, 'C' => 1],
                    'risk_by_option' => ['A' => 25, 'B' => 80, 'C' => 85],
                    'next_branching_by_option' => ['A' => 'stress_2', 'B' => 'stress_2', 'C' => 'stress_2'],
                ],
            ],
            [
                'branching_id' => 'stress_2',
                'stress_context' => 'office',
                'sort_order' => 3,
                'question' => 'Стресс-сценарий (офис): после тряски сработала пожарная сигнализация. Что сначала?',
                'options' => [
                    'A' => 'Оценить ближайшие риски и использовать безопасный путь по лестнице',
                    'B' => 'Использовать лифт, пока нет толпы',
                    'C' => 'Вернуться к столу за вещами',
                ],
                'correct_answer' => 'A',
                'wrong_explanation' => 'Лифты и задержка эвакуации увеличивают риск при вторичных происшествиях.',
                'next_branching_map' => ['A' => 'stress_3', 'B' => 'stress_3', 'C' => 'stress_3'],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 10, 'B' => 0, 'C' => 2],
                    'risk_by_option' => ['A' => 24, 'B' => 92, 'C' => 70],
                    'next_branching_by_option' => ['A' => 'stress_3', 'B' => 'stress_3', 'C' => 'stress_3'],
                ],
            ],
            [
                'branching_id' => 'stress_3',
                'stress_context' => 'office',
                'sort_order' => 4,
                'question' => 'Стресс-сценарий (офис): пришло предупреждение о повторных толчках. Следующий шаг?',
                'options' => [
                    'A' => 'Оставаться в зоне сбора и ждать официального обновления',
                    'B' => 'Вернуться в здание за ноутбуком',
                    'C' => 'Игнорировать предупреждение и разойтись',
                ],
                'correct_answer' => 'A',
                'wrong_explanation' => 'Возврат в здание до разрешения часто приводит к предотвратимым травмам.',
                'next_branching_map' => ['A' => 'final_test', 'B' => 'final_test', 'C' => 'final_test'],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 10, 'B' => 1, 'C' => 2],
                    'risk_by_option' => ['A' => 20, 'B' => 88, 'C' => 72],
                    'next_branching_by_option' => ['A' => 'final_test', 'B' => 'final_test', 'C' => 'final_test'],
                ],
            ],
            [
                'branching_id' => 'final_test',
                'stress_context' => null,
                'sort_order' => 5,
                'question' => 'Финальная проверка: что обязательно должно быть в тревожном наборе?',
                'options' => [
                    'A' => 'Вода, аптечка, фонарь, радио, пауэрбанк',
                    'B' => 'Только питьевая вода',
                    'C' => 'Только личные документы',
                ],
                'correct_answer' => 'A',
                'wrong_explanation' => 'Тревожный набор должен обеспечивать выживание, связь и первую помощь.',
                'next_branching_map' => ['A' => null, 'B' => null, 'C' => null],
                'branching_logic' => [
                    'scores_by_option' => ['A' => 10, 'B' => 3, 'C' => 2],
                    'risk_by_option' => ['A' => 20, 'B' => 60, 'C' => 65],
                    'next_branching_by_option' => ['A' => null, 'B' => null, 'C' => null],
                    'checklist_by_option' => [
                        'B' => 'Добавьте в набор аптечку, фонарь и средства связи.',
                        'C' => 'Добавьте в набор воду и базовые средства первой помощи.',
                    ],
                ],
            ],
        ];

        foreach ($baseScenarios as $scenario) {
            \App\Models\Scenario::query()->updateOrCreate(
                [
                    'module_id' => $moduleId,
                    'branching_id' => $scenario['branching_id'],
                    'stress_context' => $scenario['stress_context'],
                ],
                [
                    'question' => $scenario['question'],
                    'options' => $scenario['options'],
                    'correct_answer' => $scenario['correct_answer'],
                    'wrong_explanation' => $scenario['wrong_explanation'],
                    'next_branching_map' => $scenario['next_branching_map'],
                    'branching_logic' => $scenario['branching_logic'],
                    'sort_order' => $scenario['sort_order'],
                ]
            );
        }
    }
}
