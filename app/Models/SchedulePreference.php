<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SchedulePreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'emp_info_id',
        'preference_id',
        'maximum_hours',
        'employment_type'
    ];

    protected $casts = [
        'maximum_hours' => 'integer'
    ];

    public function empInfo()
    {
        return $this->belongsTo(EmpInfo::class);
    }

    public function preference()
    {
        return $this->belongsTo(Preference::class);
    }
}
