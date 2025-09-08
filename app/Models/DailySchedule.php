<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class DailySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_of_day',
        'emp_info_id',
        'scheduled_start_time',
        'scheduled_end_time',
        'actual_start_time',
        'actual_end_time',
        'vci',
        'status_id',
        'agree_on_exception',
        'exception_notes'
    ];

    protected $casts = [
        'date_of_day' => 'date',
        'scheduled_start_time' => 'datetime:H:i:s',
        'scheduled_end_time' => 'datetime:H:i:s',
        'actual_start_time' => 'datetime:H:i:s',
        'actual_end_time' => 'datetime:H:i:s',
        'vci' => 'boolean',
        'agree_on_exception' => 'boolean'
    ];

    /**
     * Get the employee info for this schedule
     */
    public function empInfo()
    {
        return $this->belongsTo(EmpInfo::class);
    }

    /**
     * Get the status for this schedule
     */
    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get the required skills for this schedule
     */
    public function requiredSkills()
    {
        return $this->belongsToMany(Skill::class, 'daily_schedule_skills')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    /**
     * Get the work week start date (Tuesday) for a given date
     */
    public static function getWorkWeekStart($date)
    {
        $carbon = Carbon::parse($date);
        
        // If it's Tuesday (2), return the same date
        if ($carbon->dayOfWeek === Carbon::TUESDAY) {
            return $carbon->startOfDay();
        }
        
        // If it's Monday (1), go back 6 days to get previous Tuesday
        if ($carbon->dayOfWeek === Carbon::MONDAY) {
            return $carbon->subDays(6)->startOfDay();
        }
        
        // For other days, go back to the most recent Tuesday
        $daysToSubtract = ($carbon->dayOfWeek + 5) % 7;
        return $carbon->subDays($daysToSubtract)->startOfDay();
    }

    /**
     * Get the work week end date (Monday) for a given date
     */
    public static function getWorkWeekEnd($date)
    {
        return static::getWorkWeekStart($date)->addDays(6)->endOfDay();
    }

    /**
     * Calculate scheduled hours for this entry
     */
    public function getScheduledHoursAttribute()
    {
        if (!$this->scheduled_start_time || !$this->scheduled_end_time) {
            return 0;
        }
        
        $start = Carbon::parse($this->scheduled_start_time);
        $end = Carbon::parse($this->scheduled_end_time);
        
        return $end->diffInHours($start, true);
    }

    /**
     * Calculate actual hours worked for this entry
     */
    public function getActualHoursAttribute()
    {
        if (!$this->actual_start_time || !$this->actual_end_time) {
            return 0;
        }
        
        $start = Carbon::parse($this->actual_start_time);
        $end = Carbon::parse($this->actual_end_time);
        
        return $end->diffInHours($start, true);
    }
}
