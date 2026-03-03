<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EarthquakeSafetyTestController extends Controller
{
    public function indexModules(): JsonResponse
    {
        $modules = Module::query()
            ->where('is_active', true)
            ->select(['id', 'title', 'description', 'slug'])
            ->orderBy('id')
            ->get();

        return response()->json($modules);
    }

    public function scenarios(Module $module): JsonResponse
    {
        $scenarios = $module->scenarios()
            ->orderBy('sort_order')
            ->get(['id', 'question', 'options', 'sort_order']);

        return response()->json([
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'slug' => $module->slug,
            ],
            'scenarios' => $scenarios,
        ]);
    }

    public function submit(Request $request, Module $module): JsonResponse
    {
        $validated = $request->validate([
            'participant_name' => ['nullable', 'string', 'max:255'],
            'participant_email' => ['nullable', 'email', 'max:255'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.scenario_id' => [
                'required',
                'integer',
                Rule::exists('scenarios', 'id')->where('module_id', $module->id),
            ],
            'answers.*.selected_option' => ['required', 'string', 'max:1000'],
        ]);

        $scenarios = $module->scenarios()->get()->keyBy('id');

        $score = 0;
        $answerRows = [];

        foreach ($validated['answers'] as $answer) {
            $scenario = $scenarios->get($answer['scenario_id']);

            if (!$scenario) {
                continue;
            }

            $isCorrect = $scenario->correct_option === $answer['selected_option'];

            if ($isCorrect) {
                $score++;
            }

            $answerRows[] = [
                'scenario_id' => $scenario->id,
                'selected_option' => $answer['selected_option'],
                'is_correct' => $isCorrect,
            ];
        }

        $totalQuestions = count($answerRows);
        $percentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100, 2) : 0;

        $result = DB::transaction(function () use ($module, $validated, $score, $totalQuestions, $percentage, $answerRows) {
            $result = Result::query()->create([
                'module_id' => $module->id,
                'participant_name' => $validated['participant_name'] ?? null,
                'participant_email' => $validated['participant_email'] ?? null,
                'score' => $score,
                'total_questions' => $totalQuestions,
                'percentage' => $percentage,
            ]);

            $result->answers()->createMany($answerRows);

            return $result;
        });

        return response()->json([
            'result_id' => $result->id,
            'score' => $result->score,
            'total_questions' => $result->total_questions,
            'percentage' => $result->percentage,
            'status' => $result->percentage >= 70 ? 'pass' : 'fail',
        ], 201);
    }
}