<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_name',
        'scenario_id',
        'selected_option',
        'score',
        'response_time_ms',
        'retries',
        'stress_context',
        'wrong_explanation_shown',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }
}
