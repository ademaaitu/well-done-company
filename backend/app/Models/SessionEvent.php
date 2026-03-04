<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'event_name',
        'payload',
        'event_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'event_at' => 'datetime',
    ];
}
