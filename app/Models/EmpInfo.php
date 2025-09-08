<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpInfo extends Model
{
    protected $fillable = [
        'store_id',
        'full_name',
        'date_of_birth',
        'has_family',
        'has_car',
        'is_arabic_team',
        'notes',
        'status'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'has_family' => 'boolean',
        'has_car' => 'boolean',
        'is_arabic_team' => 'boolean'
    ];

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'emp_info_skill')
            ->withPivot('rating')
            ->withTimestamps();
    }

    public function schedulePreferences()
    {
        return $this->hasMany(SchedulePreference::class);
    }

    public function employmentInfo()
    {
        return $this->hasOne(EmpEmploymentInfo::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function dailySchedules()
    {
        return $this->hasMany(DailySchedule::class);
    }
}
