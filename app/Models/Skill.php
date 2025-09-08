<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function empInfos()
    {
        return $this->belongsToMany(EmpInfo::class, 'emp_info_skill')
            ->withPivot('rating')
            ->withTimestamps();
    }

    public function dailySchedules()
    {
        return $this->belongsToMany(DailySchedule::class, 'daily_schedule_skills')
            ->withPivot('is_required')
            ->withTimestamps();
    }
}
