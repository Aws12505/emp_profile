<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WeeklyScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * Expected input structure (employee data retrieved from database):
     * {
     *     "weekly_schedule": [
     *         {
     *             "date_of_day": "2024-01-15",
     *             "schedules": [
     *                 {
     *                     "emp_info_id": 1,
     *                     "scheduled_start_time": "09:00:00",
     *                     "scheduled_end_time": "13:00:00",
     *                     "actual_start_time": null,
     *                     "actual_end_time": null,
     *                     "vci": false,
     *                     "status_id": 1,
     *                     "agree_on_exception": false,
     *                     "exception_notes": null,
     *                     "required_skills": [1, 2]
     *                 },
     *                 {
     *                     "emp_info_id": 1,
     *                     "scheduled_start_time": "14:00:00",
     *                     "scheduled_end_time": "17:00:00",
     *                     "actual_start_time": null,
     *                     "actual_end_time": null,
     *                     "vci": false,
     *                     "status_id": 1,
     *                     "agree_on_exception": false,
     *                     "exception_notes": null,
     *                     "required_skills": [1]
     *                 }
     *             ]
     *         }
     *         // ... more days in the week
     *     ]
     * }
     * 
     * Note: Employee details are retrieved from the database using emp_info_id
     * Also supports legacy individual schedule format for backward compatibility
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // Main weekly schedule array
            'weekly_schedule' => 'required|array|min:1|max:7',
        ];
        
        // Check if this is the new day-level format or legacy format
        $weeklySchedule = $this->input('weekly_schedule', []);
        $isNewFormat = !empty($weeklySchedule) && isset($weeklySchedule[0]['schedules']);
        
        if ($isNewFormat) {
            // New day-level format validation
            $rules = array_merge($rules, [
                // Day-level validation
                'weekly_schedule.*.date_of_day' => 'required|date',
                'weekly_schedule.*.schedules' => 'required|array|min:1',
                
                // Individual schedule validation within each day
                'weekly_schedule.*.schedules.*.emp_info_id' => 'required|integer|exists:emp_infos,id',
                'weekly_schedule.*.schedules.*.scheduled_start_time' => 'required|date_format:H:i:s',
                'weekly_schedule.*.schedules.*.scheduled_end_time' => 'required|date_format:H:i:s',
                'weekly_schedule.*.schedules.*.actual_start_time' => 'nullable|date_format:H:i:s',
                'weekly_schedule.*.schedules.*.actual_end_time' => 'nullable|date_format:H:i:s',
                'weekly_schedule.*.schedules.*.vci' => 'nullable|boolean',
                'weekly_schedule.*.schedules.*.status_id' => 'required|integer|exists:statuses,id',
                'weekly_schedule.*.schedules.*.agree_on_exception' => 'nullable|boolean',
                'weekly_schedule.*.schedules.*.exception_notes' => 'nullable|string|max:1000',
                'weekly_schedule.*.schedules.*.required_skills' => 'nullable|array',
                'weekly_schedule.*.schedules.*.required_skills.*' => 'integer|exists:skills,id',
                
                // Remove embedded employee data validation - backend will retrieve employee details
            ]);
        } else {
            // Legacy individual schedule format validation
            $rules = array_merge($rules, [
                'weekly_schedule.*.date_of_day' => 'required|date',
                'weekly_schedule.*.emp_info_id' => 'required|integer|exists:emp_infos,id',
                'weekly_schedule.*.scheduled_start_time' => 'required|date_format:H:i:s',
                'weekly_schedule.*.scheduled_end_time' => 'required|date_format:H:i:s|after:weekly_schedule.*.scheduled_start_time',
                'weekly_schedule.*.actual_start_time' => 'nullable|date_format:H:i:s',
                'weekly_schedule.*.actual_end_time' => 'nullable|date_format:H:i:s',
                'weekly_schedule.*.vci' => 'nullable|boolean',
                'weekly_schedule.*.status_id' => 'required|integer|exists:statuses,id',
                'weekly_schedule.*.agree_on_exception' => 'nullable|boolean',
                'weekly_schedule.*.exception_notes' => 'nullable|string|max:1000',
                'weekly_schedule.*.required_skills' => 'nullable|array',
                'weekly_schedule.*.required_skills.*' => 'integer|exists:skills,id',
                
                // Remove embedded employee data validation - backend will retrieve employee details
            ]);
        }
        
        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateEmployeeConsistency($validator);
            $this->validateTimeLogic($validator);
            $this->validateWeekDateRange($validator);
            $this->validateSplitShifts($validator);
        });
    }

    /**
     * Validate that employee IDs exist in the database
     */
    protected function validateEmployeeConsistency($validator)
    {
        // Employee data will be retrieved by backend, just validate emp_info_id exists
        // This validation is already handled by the exists:emp_infos,id rule
    }

    /**
     * Validate time logic for actual times
     */
    protected function validateTimeLogic($validator)
    {
        $weeklySchedule = $this->input('weekly_schedule', []);
        $isNewFormat = !empty($weeklySchedule) && isset($weeklySchedule[0]['schedules']);
        
        if ($isNewFormat) {
            // New day-level format validation
            foreach ($weeklySchedule as $dayIndex => $day) {
                if (isset($day['schedules'])) {
                    foreach ($day['schedules'] as $scheduleIndex => $schedule) {
                        $this->validateScheduleTimeLogic($validator, $schedule, "weekly_schedule.{$dayIndex}.schedules.{$scheduleIndex}");
                    }
                }
            }
        } else {
            // Legacy format validation
            foreach ($weeklySchedule as $index => $schedule) {
                $this->validateScheduleTimeLogic($validator, $schedule, "weekly_schedule.{$index}");
            }
        }
    }
    
    /**
     * Validate time logic for a single schedule
     */
    protected function validateScheduleTimeLogic($validator, array $schedule, string $fieldPrefix)
    {
        // Validate scheduled time logic
        if (isset($schedule['scheduled_start_time']) && isset($schedule['scheduled_end_time'])) {
            try {
                $scheduledStart = \Carbon\Carbon::createFromFormat('H:i:s', $schedule['scheduled_start_time']);
                $scheduledEnd = \Carbon\Carbon::createFromFormat('H:i:s', $schedule['scheduled_end_time']);
                
                if ($scheduledEnd->lte($scheduledStart)) {
                    $validator->errors()->add(
                        "{$fieldPrefix}.scheduled_end_time",
                        'The scheduled end time must be after the scheduled start time.'
                    );
                }
            } catch (\Exception $e) {
                // Time format validation will be handled by the date_format rule
            }
        }
        
        // Validate actual end time is after actual start time if both are provided
        if (isset($schedule['actual_start_time']) && isset($schedule['actual_end_time'])) {
            try {
                $actualStart = \Carbon\Carbon::createFromFormat('H:i:s', $schedule['actual_start_time']);
                $actualEnd = \Carbon\Carbon::createFromFormat('H:i:s', $schedule['actual_end_time']);
                
                if ($actualEnd->lte($actualStart)) {
                    $validator->errors()->add(
                        "{$fieldPrefix}.actual_end_time",
                        'The actual end time must be after the actual start time.'
                    );
                }
            } catch (\Exception $e) {
                // Time format validation will be handled by the date_format rule
            }
        }
    }

    /**
     * Validate that all dates fall within a single work week
     */
    protected function validateWeekDateRange($validator)
    {
        $weeklySchedule = $this->input('weekly_schedule', []);
        
        if (empty($weeklySchedule)) {
            return;
        }
        
        $dates = collect($weeklySchedule)
            ->pluck('date_of_day')
            ->filter()
            ->map(function ($date) {
                try {
                    return \Carbon\Carbon::parse($date);
                } catch (\Exception $e) {
                    return null;
                }
            })
            ->filter();
            
        if ($dates->isEmpty()) {
            return;
        }
        
        $minDate = $dates->min();
        $maxDate = $dates->max();
        
        // Check if all dates fall within a 7-day range
        if ($maxDate->diffInDays($minDate) > 6) {
            $validator->errors()->add(
                'weekly_schedule',
                'All schedule dates must fall within a single work week (7-day range).'
            );
        }
    }
    
    /**
     * Validate split shifts - ensure no time overlaps for the same employee on the same day
     */
    protected function validateSplitShifts($validator)
    {
        $weeklySchedule = $this->input('weekly_schedule', []);
        $isNewFormat = !empty($weeklySchedule) && isset($weeklySchedule[0]['schedules']);
        
        if ($isNewFormat) {
            // New day-level format - check for overlaps within each day
            foreach ($weeklySchedule as $dayIndex => $day) {
                if (isset($day['schedules']) && count($day['schedules']) > 1) {
                    $this->validateDayScheduleOverlaps($validator, $day['schedules'], $dayIndex);
                }
            }
        } else {
            // Legacy format - group by employee and date, then check overlaps
            $groupedSchedules = [];
            foreach ($weeklySchedule as $index => $schedule) {
                if (isset($schedule['emp_info_id']) && isset($schedule['date_of_day'])) {
                    $key = $schedule['emp_info_id'] . '_' . $schedule['date_of_day'];
                    $groupedSchedules[$key][] = ['schedule' => $schedule, 'index' => $index];
                }
            }
            
            foreach ($groupedSchedules as $group) {
                if (count($group) > 1) {
                    $this->validateLegacyScheduleOverlaps($validator, $group);
                }
            }
        }
    }
    
    /**
     * Validate overlaps for schedules within a single day (new format)
     */
    protected function validateDayScheduleOverlaps($validator, array $schedules, int $dayIndex)
    {
        // Group schedules by employee
        $employeeSchedules = [];
        foreach ($schedules as $index => $schedule) {
            if (isset($schedule['emp_info_id'])) {
                $employeeSchedules[$schedule['emp_info_id']][] = ['schedule' => $schedule, 'index' => $index];
            }
        }
        
        // Check for overlaps within each employee's schedules
        foreach ($employeeSchedules as $empId => $empSchedules) {
            if (count($empSchedules) > 1) {
                for ($i = 0; $i < count($empSchedules); $i++) {
                    for ($j = $i + 1; $j < count($empSchedules); $j++) {
                        $schedule1 = $empSchedules[$i]['schedule'];
                        $schedule2 = $empSchedules[$j]['schedule'];
                        
                        if ($this->schedulesOverlap($schedule1, $schedule2)) {
                            $validator->errors()->add(
                                "weekly_schedule.{$dayIndex}.schedules.{$empSchedules[$j]['index']}.scheduled_start_time",
                                'Schedule times overlap with another schedule for the same employee on this day.'
                            );
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Validate overlaps for legacy format schedules
     */
    protected function validateLegacyScheduleOverlaps($validator, array $group)
    {
        for ($i = 0; $i < count($group); $i++) {
            for ($j = $i + 1; $j < count($group); $j++) {
                $schedule1 = $group[$i]['schedule'];
                $schedule2 = $group[$j]['schedule'];
                
                if ($this->schedulesOverlap($schedule1, $schedule2)) {
                    $validator->errors()->add(
                        "weekly_schedule.{$group[$j]['index']}.scheduled_start_time",
                        'Schedule times overlap with another schedule for the same employee on this day.'
                    );
                }
            }
        }
    }
    
    /**
     * Check if two schedules overlap in time
     */
    protected function schedulesOverlap(array $schedule1, array $schedule2): bool
    {
        if (!isset($schedule1['scheduled_start_time'], $schedule1['scheduled_end_time'],
                   $schedule2['scheduled_start_time'], $schedule2['scheduled_end_time'])) {
            return false;
        }
        
        try {
            $start1 = \Carbon\Carbon::createFromFormat('H:i:s', $schedule1['scheduled_start_time']);
            $end1 = \Carbon\Carbon::createFromFormat('H:i:s', $schedule1['scheduled_end_time']);
            $start2 = \Carbon\Carbon::createFromFormat('H:i:s', $schedule2['scheduled_start_time']);
            $end2 = \Carbon\Carbon::createFromFormat('H:i:s', $schedule2['scheduled_end_time']);
            
            // Check if schedules overlap
            return ($start1->lt($end2)) && ($start2->lt($end1));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'weekly_schedule.required' => 'The weekly schedule data is required.',
            'weekly_schedule.array' => 'The weekly schedule must be an array.',
            'weekly_schedule.min' => 'The weekly schedule must contain at least one day.',
            'weekly_schedule.max' => 'The weekly schedule cannot contain more than 7 days.',
            
            'weekly_schedule.*.date_of_day.required' => 'Each schedule entry must have a date.',
            'weekly_schedule.*.date_of_day.date' => 'The date must be a valid date format.',
            
            'weekly_schedule.*.emp_info_id.required' => 'Each schedule entry must have an employee ID.',
            'weekly_schedule.*.emp_info_id.exists' => 'The selected employee does not exist.',
            
            'weekly_schedule.*.scheduled_start_time.required' => 'Scheduled start time is required.',
            'weekly_schedule.*.scheduled_start_time.date_format' => 'Scheduled start time must be in HH:MM:SS format.',
            
            'weekly_schedule.*.scheduled_end_time.required' => 'Scheduled end time is required.',
            'weekly_schedule.*.scheduled_end_time.date_format' => 'Scheduled end time must be in HH:MM:SS format.',
            'weekly_schedule.*.scheduled_end_time.after' => 'Scheduled end time must be after start time.',
            
            'weekly_schedule.*.actual_start_time.date_format' => 'Actual start time must be in HH:MM:SS format.',
            'weekly_schedule.*.actual_end_time.date_format' => 'Actual end time must be in HH:MM:SS format.',
            
            'weekly_schedule.*.status_id.required' => 'Status is required for each schedule entry.',
            'weekly_schedule.*.status_id.exists' => 'The selected status does not exist.',
            
            'weekly_schedule.*.required_skills.array' => 'Required skills must be an array.',
            'weekly_schedule.*.required_skills.*.exists' => 'One or more selected skills do not exist.',
            
            'weekly_schedule.*.exception_notes.max' => 'Exception notes cannot exceed 1000 characters.'
        ];
    }

    /**
     * Get custom attribute names for validation errors
     */
    public function attributes(): array
    {
        return [
            'weekly_schedule' => 'weekly schedule',
            'weekly_schedule.*.date_of_day' => 'schedule date',
            'weekly_schedule.*.emp_info_id' => 'employee ID',
            'weekly_schedule.*.scheduled_start_time' => 'scheduled start time',
            'weekly_schedule.*.scheduled_end_time' => 'scheduled end time',
            'weekly_schedule.*.actual_start_time' => 'actual start time',
            'weekly_schedule.*.actual_end_time' => 'actual end time',
            'weekly_schedule.*.status_id' => 'status',
            'weekly_schedule.*.required_skills' => 'required skills',
            'weekly_schedule.*.exception_notes' => 'exception notes'
        ];
    }
}