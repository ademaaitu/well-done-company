<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scenario extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'question',
        'options',
        'correct_option',
        'explanation',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }
}