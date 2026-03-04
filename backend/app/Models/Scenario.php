<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scenario extends Model
{
    use HasFactory;

    protected $fillable = [
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
    ];

    protected $casts = [
        'options' => 'array',
        'next_branching_map' => 'array',
        'branching_logic' => 'array',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
