<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Preference extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'slug'
    ];

    public function schedulePreferences()
    {
        return $this->hasMany(SchedulePreference::class);
    }
}
