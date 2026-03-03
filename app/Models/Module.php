<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'slug',
        'is_active',
    ];

    public function scenarios(): HasMany
    {
        return $this->hasMany(Scenario::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }
}