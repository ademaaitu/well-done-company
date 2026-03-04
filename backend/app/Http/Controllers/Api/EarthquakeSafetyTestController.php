<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\LearningSession;
use App\Models\Result;
use App\Models\Scenario;
use App\Models\SessionEvent;
use App\Models\UserAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EarthquakeSafetyTestController extends Controller
{
    public function startSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_name' => ['required', 'string', 'max:255'],
            'module_id' => ['nullable', 'integer', Rule::exists('modules', 'id')],
            'stress_context' => ['nullable', 'string', 'max:100'],
        ]);

        $sessionId = Str::uuid()->toString();

        LearningSession::query()->create([
            'session_id' => $sessionId,
            'user_name' => $validated['user_name'],
            'module_id' => $validated['module_id'] ?? null,
            'stress_context' => $validated['stress_context'] ?? null,
            'started_at' => now(),
            'last_event_at' => now(),
            'metadata' => ['source' => 'frontend'],
        ]);

        return response()->json([
            'session_id' => $sessionId,
            'started_at' => now()->toISOString(),
        ], 201);
    }

    public function resources(): JsonResponse
    {
        return response()->json([
            'checklist' => [
                'Пригнуться, укрыться и держаться',
                'Поддерживайте тревожный набор в готовности',
                'Знайте безопасные зоны дома, в ТЦ и на транспорте',
                'Отработайте минимум два маршрута эвакуации',
                'Храните экстренные контакты офлайн',
            ],
            'resources' => [
                ['title' => 'USGS: Опасности землетрясений', 'url' => 'https://earthquake.usgs.gov/'],
                ['title' => 'Ready.gov: Подготовка к землетрясению', 'url' => 'https://www.ready.gov/earthquakes'],
                ['title' => 'IFRC: Подготовка к землетрясениям', 'url' => 'https://www.ifrc.org/our-work/disasters-climate-and-crises/earthquakes'],
            ],
        ]);
    }

    public function trackSessionEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:100'],
            'event_name' => ['required', 'string', 'max:100'],
            'payload' => ['nullable', 'array'],
        ]);

        SessionEvent::query()->create([
            'session_id' => $validated['session_id'],
            'event_name' => $validated['event_name'],
            'payload' => $validated['payload'] ?? null,
            'event_at' => now(),
        ]);

        LearningSession::query()
            ->where('session_id', $validated['session_id'])
            ->update(['last_event_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    public function modules(): JsonResponse
    {
        $modules = Module::query()
            ->select(['id', 'name', 'description'])
            ->orderBy('id')
            ->get();

        return response()->json($modules);
    }

    public function scenarios(Request $request, int $id): JsonResponse
    {
        $module = Module::query()->findOrFail($id);
        $stressContext = $request->query('stress_context');

        $scenarios = $module->scenarios()
            ->when($stressContext, function ($query) use ($stressContext) {
                $query->where(function ($nested) use ($stressContext) {
                    $nested
                        ->whereNull('stress_context')
                        ->orWhere('stress_context', $stressContext);
                });
            })
            ->orderBy('sort_order')
            ->get([
                'id',
                'module_id',
                'branching_id',
                'stress_context',
                'question',
                'options',
                'correct_answer',
                'wrong_explanation',
                'next_branching_map',
                'branching_logic',
                'sort_order',
            ]);

        return response()->json($scenarios);
    }

    public function nextScenario(Request $request, int $id): JsonResponse
    {
        $module = Module::query()->findOrFail($id);

        $validated = $request->validate([
            'stress_context' => ['required', 'string', 'max:100'],
            'current_branching_id' => ['nullable', 'string', 'max:100'],
            'selected_option' => ['nullable', 'string', 'max:100'],
        ]);

        if (empty($validated['current_branching_id'])) {
            $start = $module->scenarios()
                ->where('branching_id', 'start')
                ->whereNull('stress_context')
                ->orderBy('sort_order')
                ->first();

            return response()->json($start);
        }

        $currentScenario = $module->scenarios()
            ->where('branching_id', $validated['current_branching_id'])
            ->where(function ($query) use ($validated) {
                $query
                    ->whereNull('stress_context')
                    ->orWhere('stress_context', $validated['stress_context']);
            })
            ->first();

        if (!$currentScenario) {
            return response()->json(null);
        }

        $selectedOption = $validated['selected_option'] ?? '';
        $nextMap = $currentScenario->next_branching_map ?? [];
        $logicMap = $currentScenario->branching_logic['next_branching_by_option'] ?? [];

        $nextBranchingId = $nextMap[$selectedOption] ?? $logicMap[$selectedOption] ?? null;

        if (!$nextBranchingId) {
            return response()->json(null);
        }

        $nextScenario = $module->scenarios()
            ->where('branching_id', $nextBranchingId)
            ->where(function ($query) use ($validated) {
                $query
                    ->whereNull('stress_context')
                    ->orWhere('stress_context', $validated['stress_context']);
            })
            ->orderBy('sort_order')
            ->first();

        return response()->json($nextScenario);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $module = Module::query()->findOrFail($id);

        $validated = $request->validate([
            'user_name' => ['required', 'string', 'max:255'],
            'stress_context' => ['required', 'string', 'max:100'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.scenario_id' => [
                'required',
                'integer',
                Rule::exists('scenarios', 'id')->where(fn ($query) => $query->where('module_id', $module->id)),
            ],
            'answers.*.selected_option' => ['required', 'string', 'max:255'],
            'answers.*.response_time_ms' => ['nullable', 'integer', 'min:0'],
            'answers.*.retries' => ['nullable', 'integer', 'min:0'],
            'answers.*.wrong_explanation_shown' => ['nullable', 'boolean'],
        ]);

        $sessionId = $validated['session_id'] ?? Str::uuid()->toString();
        $scenarios = $module->scenarios()->get()->keyBy('id');

        $totalScore = 0;
        $correctCount = 0;
        $riskSum = 0;
        $stressScoreSum = 0;
        $stressScenarioCount = 0;
        $responseTimeSum = 0;
        $retrySum = 0;
        $answerRows = [];
        $progress = [];
        $wrongReasons = [];
        $dynamicChecklist = [];

        foreach ($validated['answers'] as $answer) {
            $scenario = $scenarios->get($answer['scenario_id']);

            if (!$scenario) {
                continue;
            }

            $selectedOption = $answer['selected_option'];
            $isCorrect = $scenario->correct_answer === $selectedOption;

            $logic = is_array($scenario->branching_logic) ? $scenario->branching_logic : [];
            $scoresByOption = $logic['scores_by_option'] ?? [];
            $riskByOption = $logic['risk_by_option'] ?? [];
            $hintByOption = $logic['hint_by_option'] ?? [];
            $checklistByOption = $logic['checklist_by_option'] ?? [];

            $score = array_key_exists($selectedOption, $scoresByOption)
                ? (int) $scoresByOption[$selectedOption]
                : ($isCorrect ? 10 : 0);

            $riskValue = array_key_exists($selectedOption, $riskByOption)
                ? (int) $riskByOption[$selectedOption]
                : ($isCorrect ? 20 : 80);

            if ($isCorrect) {
                $correctCount++;
            } else {
                $wrongReasons[] = $scenario->wrong_explanation ?: ($hintByOption[$selectedOption] ?? 'Пересмотрите этот сценарий безопасного поведения.');
            }

            if (!empty($checklistByOption[$selectedOption])) {
                $dynamicChecklist[] = (string) $checklistByOption[$selectedOption];
            }

            $totalScore += $score;
            $riskSum += $riskValue;
            $responseTime = (int) ($answer['response_time_ms'] ?? 0);
            $retries = (int) ($answer['retries'] ?? 0);
            $responseTimeSum += $responseTime;
            $retrySum += $retries;

            if ($scenario->stress_context === $validated['stress_context']) {
                $stressScoreSum += $score;
                $stressScenarioCount++;
            }

            $answerRows[] = [
                'session_id' => $sessionId,
                'user_name' => $validated['user_name'],
                'scenario_id' => $scenario->id,
                'selected_option' => $selectedOption,
                'score' => $score,
                'response_time_ms' => $responseTime,
                'retries' => $retries,
                'stress_context' => $validated['stress_context'],
                'wrong_explanation_shown' => (bool) ($answer['wrong_explanation_shown'] ?? false),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $progress[] = [
                'scenario_id' => $scenario->id,
                'branching_id' => $scenario->branching_id,
                'selected_option' => $selectedOption,
                'score' => $score,
                'is_correct' => $isCorrect,
                'response_time_ms' => $responseTime,
                'retries' => $retries,
            ];
        }

        $answeredTotal = max(count($answerRows), 1);
        $accuracyScore = (int) round(($correctCount / $answeredTotal) * 100);
        $reactionRiskIndex = (int) round($riskSum / $answeredTotal);
        $stressResponseScore = $stressScenarioCount > 0
            ? (int) round(($stressScoreSum / ($stressScenarioCount * 10)) * 100)
            : $accuracyScore;

        $overallPreparednessPercent = (int) round(
            ($accuracyScore * 0.5) + ((100 - $reactionRiskIndex) * 0.25) + ($stressResponseScore * 0.25)
        );

        $riskCategory = $reactionRiskIndex >= 67
            ? 'Высокий'
            : ($reactionRiskIndex >= 34 ? 'Умеренный' : 'Низкий');

        $recommendation = $overallPreparednessPercent >= 80
            ? 'Отлично'
            : ($overallPreparednessPercent >= 55 ? 'Хорошо' : 'Требуется улучшение');

        $behavioralAnalysis = empty($wrongReasons)
            ? 'Ваши решения показывают устойчивую осведомлённость о рисках и контролируемую реакцию в стрессовых условиях.'
            : 'Выявлены зоны для улучшения поведения: '.implode(' ', array_slice(array_unique($wrongReasons), 0, 3));

        $fallbackChecklist = [
            'Тренируйте «Пригнуться, укрыться и держаться» в разных условиях.',
            'Подготовьте семейный план связи и список экстренных контактов.',
            'Проверьте маршруты эвакуации дома, в общественных местах и на транспорте.',
            'Держите тревожный набор: вода, фонарь, аптечка и пауэрбанк.',
        ];

        $personalizedChecklist = array_values(array_unique(array_merge($dynamicChecklist, $fallbackChecklist)));

        $sessionJson = [
            'session_id' => $sessionId,
            'user_name' => $validated['user_name'],
            'module_id' => $module->id,
            'stress_context' => $validated['stress_context'],
            'metrics' => [
                'accuracy_score' => $accuracyScore,
                'reaction_risk_index' => $reactionRiskIndex,
                'stress_response_score' => $stressResponseScore,
                'overall_preparedness_percent' => $overallPreparednessPercent,
                'avg_response_time_ms' => (int) round($responseTimeSum / $answeredTotal),
                'avg_retries' => (float) round($retrySum / $answeredTotal, 2),
            ],
            'progress' => $progress,
        ];

        $result = DB::transaction(function () use (
            $module,
            $validated,
            $sessionId,
            $totalScore,
            $accuracyScore,
            $reactionRiskIndex,
            $stressResponseScore,
            $overallPreparednessPercent,
            $riskCategory,
            $behavioralAnalysis,
            $personalizedChecklist,
            $recommendation,
            $progress,
            $sessionJson,
            $answerRows
        ) {
            $result = Result::query()->create([
                'user_name' => $validated['user_name'],
                'module_id' => $module->id,
                'session_id' => $sessionId,
                'stress_context' => $validated['stress_context'],
                'total_score' => $totalScore,
                'accuracy_score' => $accuracyScore,
                'reaction_risk_index' => $reactionRiskIndex,
                'stress_response_score' => $stressResponseScore,
                'overall_preparedness_percent' => $overallPreparednessPercent,
                'risk_category' => $riskCategory,
                'behavioral_analysis' => $behavioralAnalysis,
                'personalized_checklist' => $personalizedChecklist,
                'recommendation' => $recommendation,
                'progress' => $progress,
                'session_json' => $sessionJson,
            ]);

            if (!empty($answerRows)) {
                UserAnswer::query()->insert($answerRows);
            }

            LearningSession::query()
                ->where('session_id', $sessionId)
                ->update([
                    'module_id' => $module->id,
                    'stress_context' => $validated['stress_context'],
                    'completed_at' => now(),
                    'last_event_at' => now(),
                    'metadata' => [
                        'overall_preparedness_percent' => $overallPreparednessPercent,
                        'risk_category' => $riskCategory,
                    ],
                ]);

            return $result;
        });

        return response()->json([
            'session_id' => $result->session_id,
            'user_name' => $result->user_name,
            'module_id' => $result->module_id,
            'total_score' => $result->total_score,
            'accuracy_score' => $result->accuracy_score,
            'reaction_risk_index' => $result->reaction_risk_index,
            'stress_response_score' => $result->stress_response_score,
            'overall_preparedness_percent' => $result->overall_preparedness_percent,
            'risk_category' => $result->risk_category,
            'behavioral_analysis' => $result->behavioral_analysis,
            'personalized_checklist' => $result->personalized_checklist,
            'recommendation' => $result->recommendation,
        ]);
    }

    public function results(string $user_name): JsonResponse
    {
        $results = Result::query()
            ->where('user_name', $user_name)
            ->orderByDesc('id')
            ->get([
                'id',
                'session_id',
                'user_name',
                'module_id',
                'stress_context',
                'total_score',
                'accuracy_score',
                'reaction_risk_index',
                'stress_response_score',
                'overall_preparedness_percent',
                'risk_category',
                'behavioral_analysis',
                'personalized_checklist',
                'recommendation',
                'progress',
                'session_json',
            ]);

        return response()->json($results);
    }

    public function analyticsSummary(): JsonResponse
    {
        $resultSummary = Result::query()
            ->selectRaw('COUNT(*) as total_sessions')
            ->selectRaw('AVG(accuracy_score) as avg_accuracy_score')
            ->selectRaw('AVG(reaction_risk_index) as avg_reaction_risk_index')
            ->selectRaw('AVG(stress_response_score) as avg_stress_response_score')
            ->selectRaw('AVG(overall_preparedness_percent) as avg_overall_preparedness_percent')
            ->first();

        $answerSummary = UserAnswer::query()
            ->selectRaw('AVG(response_time_ms) as avg_response_time_ms')
            ->selectRaw('AVG(retries) as avg_retries')
            ->first();

        return response()->json([
            'total_sessions' => (int) ($resultSummary->total_sessions ?? 0),
            'avg_accuracy_score' => (float) round($resultSummary->avg_accuracy_score ?? 0, 2),
            'avg_reaction_risk_index' => (float) round($resultSummary->avg_reaction_risk_index ?? 0, 2),
            'avg_stress_response_score' => (float) round($resultSummary->avg_stress_response_score ?? 0, 2),
            'avg_overall_preparedness_percent' => (float) round($resultSummary->avg_overall_preparedness_percent ?? 0, 2),
            'avg_response_time_ms' => (float) round($answerSummary->avg_response_time_ms ?? 0, 2),
            'avg_retries' => (float) round($answerSummary->avg_retries ?? 0, 2),
        ]);
    }

    public function riskDistribution(): JsonResponse
    {
        $distribution = Result::query()
            ->select('risk_category', DB::raw('COUNT(*) as total'))
            ->groupBy('risk_category')
            ->orderBy('risk_category')
            ->get();

        return response()->json($distribution);
    }

    public function prototypeConfig(): JsonResponse
    {
        return response()->json($this->prototypeContent());
    }

    public function prototypeSubmit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:100'],
            'user_name' => ['required', 'string', 'max:255'],
            'scenario_path' => ['required', 'array', 'min:1'],
            'scenario_final_id' => ['required', 'string', 'max:50'],
            'stress_answers' => ['required', 'array'],
            'quiz_answers' => ['required', 'array', 'size:5'],
        ]);

        $content = $this->prototypeContent();
        $finals = collect($content['scenario']['finals'])->keyBy('id');
        $quizQuestions = collect($content['quiz']['questions'])->keyBy('id');

        $selectedFinal = $finals->get($validated['scenario_final_id']);
        if (!$selectedFinal) {
            return response()->json(['message' => 'Неверный финал сценария.'], 422);
        }

        $scenarioScore = (int) ($selectedFinal['score'] ?? 0);
        $scenarioRisk = $selectedFinal['risk_level'] ?? 'high';

        $stressAnswerConfig = $content['stress_case']['answer_key'];
        $stressScoreTotal = 0;
        $stressMax = 0;

        foreach ($stressAnswerConfig as $key => $map) {
            $stressMax += 100;
            $selected = $validated['stress_answers'][$key] ?? null;
            $stressScoreTotal += (int) ($map[$selected] ?? 0);
        }

        $stressScore = (int) round(($stressScoreTotal / max($stressMax, 1)) * 100);

        $correctQuizCount = 0;
        foreach ($validated['quiz_answers'] as $entry) {
            $questionId = $entry['question_id'] ?? null;
            $selectedOption = $entry['selected_option'] ?? null;
            $question = $quizQuestions->get($questionId);

            if ($question && $question['correct_option'] === $selectedOption) {
                $correctQuizCount++;
            }
        }

        $quizScore = (int) round(($correctQuizCount / 5) * 100);
        $overall = (int) round(($scenarioScore * 0.35) + ($stressScore * 0.35) + ($quizScore * 0.30));

        $riskCategory = $overall >= 80
            ? 'Низкий'
            : ($overall >= 55 ? 'Умеренный' : 'Высокий');

        $gaps = [];
        if ($scenarioRisk === 'high') {
            $gaps[] = 'действия в толпе и выбор безопасного укрытия';
        }
        if ($stressScore < 60) {
            $gaps[] = 'управление эвакуацией и приоритизация в условиях дефицита времени';
        }
        if ($quizScore < 60) {
            $gaps[] = 'базовые правила поведения при землетрясении';
        }

        $analysis = empty($gaps)
            ? 'Вы действуете последовательно и сохраняете контроль в сложной обстановке ТРЦ.'
            : 'Рекомендуется усилить следующие зоны: '.implode('; ', $gaps).'.';

        $recommendation = $overall >= 80
            ? 'Высокая готовность. Назначьте короткие тренировки для команды и закрепите роли на случай эвакуации.'
            : ($overall >= 55
                ? 'Средняя готовность. Проведите повторный разбор сценариев паники и отработайте действия при сбое связи.'
                : 'Низкая готовность. Нужен интенсивный тренинг: укрытие, маршруты, распределение постов и алгоритм экстренного оповещения.');

        $personalizedChecklist = [
            'Закрепите зоны ответственности 6 сотрудников охраны на случай повторного толчка.',
            'Проведите тренировку эвакуации атриума без создания давки.',
            'Отработайте протокол при запахе газа: отключение источников, изоляция зоны, вызов служб.',
            'Подготовьте короткие шаблоны команд для рации при нестабильной связи.',
        ];

        if ($scenarioRisk === 'high') {
            $personalizedChecklist[] = 'Повторите сценарий у эскалатора: уход от толпы и укрытие вдали от стекла.';
        }

        LearningSession::query()
            ->where('session_id', $validated['session_id'])
            ->update([
                'completed_at' => now(),
                'last_event_at' => now(),
                'stress_context' => 'mall_staff',
                'metadata' => [
                    'module_type' => 'prototype_trc',
                    'overall_preparedness_percent' => $overall,
                    'risk_category' => $riskCategory,
                    'scenario_final_id' => $validated['scenario_final_id'],
                    'scenario_score' => $scenarioScore,
                    'stress_score' => $stressScore,
                    'quiz_score' => $quizScore,
                    'quiz_correct_count' => $correctQuizCount,
                    'scenario_path' => $validated['scenario_path'],
                    'stress_answers' => $validated['stress_answers'],
                    'quiz_answers' => $validated['quiz_answers'],
                ],
            ]);

        SessionEvent::query()->create([
            'session_id' => $validated['session_id'],
            'event_name' => 'prototype_completed',
            'payload' => [
                'overall_preparedness_percent' => $overall,
                'risk_category' => $riskCategory,
            ],
            'event_at' => now(),
        ]);

        return response()->json([
            'session_id' => $validated['session_id'],
            'user_name' => $validated['user_name'],
            'overall_preparedness_percent' => $overall,
            'scenario_score' => $scenarioScore,
            'stress_score' => $stressScore,
            'quiz_score' => $quizScore,
            'quiz_correct_count' => $correctQuizCount,
            'risk_category' => $riskCategory,
            'behavioral_analysis' => $analysis,
            'recommendation' => $recommendation,
            'personalized_checklist' => array_values(array_unique($personalizedChecklist)),
            'scenario_outcome' => $selectedFinal,
        ]);
    }

    public function prototypeAnalytics(): JsonResponse
    {
        $sessions = LearningSession::query()
            ->whereNotNull('metadata')
            ->where('metadata->module_type', 'prototype_trc')
            ->get(['id', 'metadata', 'created_at']);

        $total = $sessions->count();
        if ($total === 0) {
            return response()->json([
                'total_sessions' => 0,
                'avg_overall_preparedness_percent' => 0,
                'avg_stress_score' => 0,
                'avg_quiz_score' => 0,
                'risk_distribution' => [],
            ]);
        }

        $avgOverall = round($sessions->avg(fn ($item) => (float) ($item->metadata['overall_preparedness_percent'] ?? 0)), 2);
        $avgStress = round($sessions->avg(fn ($item) => (float) ($item->metadata['stress_score'] ?? 0)), 2);
        $avgQuiz = round($sessions->avg(fn ($item) => (float) ($item->metadata['quiz_score'] ?? 0)), 2);

        $riskDistribution = $sessions
            ->groupBy(fn ($item) => $item->metadata['risk_category'] ?? 'Не определён')
            ->map(fn ($group, $risk) => ['risk_category' => $risk, 'total' => $group->count()])
            ->values();

        return response()->json([
            'total_sessions' => $total,
            'avg_overall_preparedness_percent' => $avgOverall,
            'avg_stress_score' => $avgStress,
            'avg_quiz_score' => $avgQuiz,
            'risk_distribution' => $riskDistribution,
        ]);
    }

    private function prototypeContent(): array
    {
        return [
            'audience' => 'Сотрудники ТРЦ',
            'title' => 'Проверь себя: готов ли ты к землетрясению?',
            'intro' => [
                'headline' => 'Ты на смене в торговом центре',
                'attention' => 'В экстренной ситуации именно сотрудники ТРЦ первыми влияют на безопасность посетителей.',
                'cta' => 'Пройди мини-модуль и узнай, насколько ты готов действовать без паники.',
            ],
            'scenario' => [
                'nodes' => [
                    [
                        'id' => 's1',
                        'title' => 'СИТУАЦИЯ 1',
                        'text' => 'Ты находишься на 2 этаже торгового центра. Начинается сильная тряска. Люди кричат, падают витрины, гаснет часть света.',
                        'options' => [
                            ['id' => 'A', 'label' => 'Бежать к эскалатору и выходу', 'next' => 's2A'],
                            ['id' => 'B', 'label' => 'Прижаться к несущей колонне вдали от витрин', 'next' => 's2B'],
                        ],
                    ],
                    [
                        'id' => 's2A',
                        'title' => 'СИТУАЦИЯ 2A',
                        'text' => 'Люди в панике толкаются. Эскалатор резко останавливается.',
                        'options' => [
                            ['id' => 'A1', 'label' => 'Продолжать спускаться по эскалатору', 'next' => 's3A1'],
                            ['id' => 'A2', 'label' => 'Отойти в сторону и переждать толчки', 'next' => 's3A2'],
                        ],
                    ],
                    [
                        'id' => 's3A1',
                        'title' => 'СИТУАЦИЯ 3A1',
                        'text' => 'Толпа ускоряется. Кто-то падает перед тобой.',
                        'options' => [
                            ['id' => 'A1a', 'label' => 'Перепрыгнуть и бежать дальше', 'next' => 'final1'],
                            ['id' => 'A1b', 'label' => 'Остановиться и удержаться за перила', 'next' => 'final2'],
                        ],
                    ],
                    [
                        'id' => 's3A2',
                        'title' => 'СИТУАЦИЯ 3A2',
                        'text' => 'Толчки усиливаются. Над тобой декоративный потолок.',
                        'options' => [
                            ['id' => 'A2a', 'label' => 'Закрыть голову и прижаться к стене', 'next' => 'final3'],
                            ['id' => 'A2b', 'label' => 'Всё равно рвануть к лестнице', 'next' => 'final4'],
                        ],
                    ],
                    [
                        'id' => 's2B',
                        'title' => 'СИТУАЦИЯ 2B',
                        'text' => 'Рядом витрина с техникой начинает рассыпаться.',
                        'options' => [
                            ['id' => 'B1', 'label' => 'Отбежать дальше от стекла', 'next' => 's3B1'],
                            ['id' => 'B2', 'label' => 'Остаться на месте', 'next' => 's3B2'],
                        ],
                    ],
                    [
                        'id' => 's3B1',
                        'title' => 'СИТУАЦИЯ 3B1',
                        'text' => 'Ты видишь ребёнка, который плачет рядом.',
                        'options' => [
                            ['id' => 'B1a', 'label' => 'Помочь ребёнку укрыться', 'next' => 'final5'],
                            ['id' => 'B1b', 'label' => 'Игнорировать и думать только о себе', 'next' => 'final6'],
                        ],
                    ],
                    [
                        'id' => 's3B2',
                        'title' => 'СИТУАЦИЯ 3B2',
                        'text' => 'Стекло разбивается, осколки летят в твою сторону.',
                        'options' => [
                            ['id' => 'B2a', 'label' => 'Закрыть лицо руками и пригнуться', 'next' => 'final7'],
                            ['id' => 'B2b', 'label' => 'Попытаться убежать в последний момент', 'next' => 'final8'],
                        ],
                    ],
                ],
                'finals' => [
                    ['id' => 'final1', 'label' => '🔴 Финал 1', 'outcome' => 'Ты теряешь равновесие, падаешь вместе с другими.', 'result' => 'Исход: критические травмы.', 'risk_level' => 'high', 'score' => 10],
                    ['id' => 'final2', 'label' => '🟡 Финал 2', 'outcome' => 'Ты удержался, но получил травму ноги. Эвакуация затруднена.', 'result' => 'Исход: выжил, но с травмой.', 'risk_level' => 'medium', 'score' => 45],
                    ['id' => 'final3', 'label' => '🟢 Финал 3', 'outcome' => 'Часть потолка падает рядом, но ты защищён. После окончания толчков спокойно эвакуируешься.', 'result' => 'Исход: выжил.', 'risk_level' => 'low', 'score' => 90],
                    ['id' => 'final4', 'label' => '🔴 Финал 4', 'outcome' => 'Во время бега падает конструкция.', 'result' => 'Исход: тяжёлые травмы.', 'risk_level' => 'high', 'score' => 12],
                    ['id' => 'final5', 'label' => '🟢 Финал 5', 'outcome' => 'Вы оба укрылись у несущей стены. После толчков находите безопасный выход.', 'result' => 'Исход: выжил и помог другим.', 'risk_level' => 'low', 'score' => 95],
                    ['id' => 'final6', 'label' => '🟡 Финал 6', 'outcome' => 'Ты в безопасности, но ребёнок получает травму.', 'result' => 'Исход: выжил, но моральная ответственность.', 'risk_level' => 'medium', 'score' => 55],
                    ['id' => 'final7', 'label' => '🟡 Финал 7', 'outcome' => 'Получаешь порезы, но избегаешь серьёзной травмы.', 'result' => 'Исход: выжил с лёгкими травмами.', 'risk_level' => 'medium', 'score' => 60],
                    ['id' => 'final8', 'label' => '🔴 Финал 8', 'outcome' => 'Поскальзываешься на осколках во время толчка.', 'result' => 'Исход: серьёзная травма.', 'risk_level' => 'high', 'score' => 15],
                ],
            ],
            'stress_case' => [
                'title' => 'СТРЕСС-КЕЙС: Ты — старший смены. 4 минуты до возможного обрушения',
                'timer_seconds' => 240,
                'context' => [
                    'В 18:42 происходит сильный толчок. Система оповещения работает частично, свет моргает.',
                    'Через 40 секунд инженер сообщает о риске повреждения несущих элементов в зоне атриума.',
                    'В атриуме детское мероприятие (~70 человек), в лифте застряли 3 человека, в фуд-корте паника, есть сообщение о запахе газа.',
                    'У тебя 6 сотрудников охраны, 1 медик, рация, связь с МЧС нестабильна.',
                ],
                'questions' => [
                    [
                        'id' => 'priority',
                        'text' => '1) Что приоритетно в первые минуты?',
                        'options' => [
                            ['id' => 'atrium', 'label' => 'Стабилизация атриума и запуск управляемой эвакуации'],
                            ['id' => 'lift_first', 'label' => 'Сначала только лифт, остальные зоны ждать'],
                            ['id' => 'wait', 'label' => 'Ждать подтверждения и ничего не запускать'],
                        ],
                    ],
                    [
                        'id' => 'team_split',
                        'text' => '2) Как распределить 6 сотрудников?',
                        'options' => [
                            ['id' => 'balanced', 'label' => '3 — атриум, 2 — фуд-корт, 1 — периметр/газ'],
                            ['id' => 'all_atrium', 'label' => 'Все 6 в атриум'],
                            ['id' => 'all_lift', 'label' => 'Все 6 к лифту'],
                        ],
                    ],
                    [
                        'id' => 'evacuation',
                        'text' => '3) Объявлять ли немедленную эвакуацию?',
                        'options' => [
                            ['id' => 'controlled', 'label' => 'Да, по секторам и с командами через рацию'],
                            ['id' => 'full_panic', 'label' => 'Да, общим сигналом сразу для всех'],
                            ['id' => 'no', 'label' => 'Нет, пока не будет официального подтверждения'],
                        ],
                    ],
                    [
                        'id' => 'responsibility',
                        'text' => '4) Какое решение по ответственности ты фиксируешь?',
                        'options' => [
                            ['id' => 'take', 'label' => 'Беру ответственность, фиксирую приказы и запускаю план'],
                            ['id' => 'delegate', 'label' => 'Перекладываю решение на старшего инженера'],
                            ['id' => 'delay', 'label' => 'Откладываю решение до повторного толчка'],
                        ],
                    ],
                ],
                'answer_key' => [
                    'priority' => ['atrium' => 100, 'lift_first' => 45, 'wait' => 10],
                    'team_split' => ['balanced' => 100, 'all_atrium' => 50, 'all_lift' => 25],
                    'evacuation' => ['controlled' => 100, 'full_panic' => 35, 'no' => 20],
                    'responsibility' => ['take' => 100, 'delegate' => 45, 'delay' => 10],
                ],
            ],
            'quiz' => [
                'title' => 'Тест: Готов ли ты к землетрясению?',
                'questions' => [
                    [
                        'id' => 'q1',
                        'text' => 'Ты в торговом центре. Началась сильная тряска. Что делать?',
                        'options' => [
                            ['id' => 'A', 'label' => 'Бежать к выходу, пока не началась паника'],
                            ['id' => 'B', 'label' => 'Спрятаться под витрину или рядом с колонной'],
                            ['id' => 'C', 'label' => 'Отойти от стекла, присесть у несущей стены и закрыть голову'],
                            ['id' => 'D', 'label' => 'Подняться на эскалатор, чтобы быть выше толпы'],
                        ],
                        'correct_option' => 'C',
                    ],
                    [
                        'id' => 'q2',
                        'text' => 'Землетрясение началось ночью. Ты в квартире на 8 этаже.',
                        'options' => [
                            ['id' => 'A', 'label' => 'Быстро выбежать в подъезд и спускаться по лестнице'],
                            ['id' => 'B', 'label' => 'Остаться в комнате, укрыться возле внутренней стены, защитить голову'],
                            ['id' => 'C', 'label' => 'Встать в дверной проём и ждать окончания'],
                            ['id' => 'D', 'label' => 'Выбежать на балкон — там больше воздуха'],
                        ],
                        'correct_option' => 'B',
                    ],
                    [
                        'id' => 'q3',
                        'text' => 'Ты в машине во время толчков.',
                        'options' => [
                            ['id' => 'A', 'label' => 'Остановиться прямо на дороге и выбежать'],
                            ['id' => 'B', 'label' => 'Остановиться вдали от мостов, зданий и ЛЭП и остаться в машине'],
                            ['id' => 'C', 'label' => 'Продолжить ехать быстрее, чтобы уехать из зоны'],
                            ['id' => 'D', 'label' => 'Припарковаться под эстакадой — там меньше падающих предметов'],
                        ],
                        'correct_option' => 'B',
                    ],
                    [
                        'id' => 'q4',
                        'text' => 'После окончания толчков ты почувствовал запах газа в квартире.',
                        'options' => [
                            ['id' => 'A', 'label' => 'Открыть окна и включить свет, чтобы проверить'],
                            ['id' => 'B', 'label' => 'Проверить плиту, затем позвонить соседям'],
                            ['id' => 'C', 'label' => 'Не включать электричество, перекрыть газ, выйти и вызвать службу'],
                            ['id' => 'D', 'label' => 'Зажечь зажигалку, чтобы проверить утечку'],
                        ],
                        'correct_option' => 'C',
                    ],
                    [
                        'id' => 'q5',
                        'text' => 'Ты оказался под завалами, телефон разряжается.',
                        'options' => [
                            ['id' => 'A', 'label' => 'Кричать постоянно, пока не охрипнешь'],
                            ['id' => 'B', 'label' => 'Стучать по трубам или стенам периодически и экономить силы'],
                            ['id' => 'C', 'label' => 'Пытаться активно разбирать завал любой ценой'],
                            ['id' => 'D', 'label' => 'Лежать без движения и ждать'],
                        ],
                        'correct_option' => 'B',
                    ],
                ],
            ],
        ];
    }
}
