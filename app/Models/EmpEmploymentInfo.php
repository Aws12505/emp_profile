<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmpEmploymentInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'emp_info_id',
        'position_id',
        'paychex_ids',
        'employment_type',
        'hired_date',
        'base_pay',
        'performance_pay',
        'has_uniform'
    ];

    protected $casts = [
        'paychex_ids' => 'array',
        'hired_date' => 'date',
        'base_pay' => 'decimal:2',
        'performance_pay' => 'decimal:2',
        'has_uniform' => 'boolean'
    ];

    public function empInfo()
    {
        return $this->belongsTo(EmpInfo::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}
