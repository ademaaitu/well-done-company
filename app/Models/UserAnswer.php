<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'result_id',
        'scenario_id',
        'selected_option',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }
}