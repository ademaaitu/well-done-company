<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_name',
        'module_id',
        'stress_context',
        'started_at',
        'completed_at',
        'last_event_at',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_event_at' => 'datetime',
        'metadata' => 'array',
    ];
}
