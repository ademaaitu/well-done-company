<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_name',
        'module_id',
        'session_id',
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
    ];

    protected $casts = [
        'progress' => 'array',
        'personalized_checklist' => 'array',
        'session_json' => 'array',
    ];
}
