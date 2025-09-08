<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $fillable = [
        'description',
        'slug'
    ];

    public function dailySchedules()
    {
        return $this->hasMany(DailySchedule::class);
    }
}
