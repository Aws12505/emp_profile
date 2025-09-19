<?php

namespace App\Services;

use App\Models\DailySchedule;
use App\Models\EmpInfo;
use App\Models\Skill;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WeeklyScheduleService
{
    /**
     * Validate and process a complete weekly schedule array
     * 
     * Expected data structure (new day-level format):
     * [
     *     [
     *         'date_of_day' => '2024-01-15',
     *         'schedules' => [
     *             [
     *                 'emp_info_id' => 1,
     *                 'scheduled_start_time' => '09:00:00',
     *                 'scheduled_end_time' => '13:00:00',
     *                 'status_id' => 1,
     *                 'required_skills' => [1, 2]
     *             ],
     *             [
     *                 'emp_info_id' => 1,
     *                 'scheduled_start_time' => '14:00:00',
     *                 'scheduled_end_time' => '17:00:00',
     *                 'status_id' => 1,
     *                 'required_skills' => [1]
     *             ]
     *         ]
     *     ],
     *     // ... more days in the week
     * ]
     * 
     * Employee data is retrieved from database based on emp_info_id
     * Also supports legacy individual schedule format for backward compatibility
     */
    public function validateAndProcessWeeklySchedule(array $weeklyScheduleData): array
    {
        // Normalize data to flat structure for validation
        $flatScheduleData = $this->normalizeWeeklyScheduleData($weeklyScheduleData);
        
        $validationResult = $this->validateWeeklySchedule($flatScheduleData);
        
        $processedSchedules = [];
        $hasViolations = !$validationResult['valid'];
        
        // Process each day's schedules
        foreach ($weeklyScheduleData as $dayData) {
            if (isset($dayData['schedules'])) {
                // New day-level format
                foreach ($dayData['schedules'] as $scheduleData) {
                    $scheduleData['date_of_day'] = $dayData['date_of_day'];
                    
                    // Retrieve employee data from database
                    $employee = EmpInfo::with('skills')->find($scheduleData['emp_info_id']);
                    if (!$employee) {
                        throw new \Exception("Employee with ID {$scheduleData['emp_info_id']} not found");
                    }
                    
                    // If there are validation violations, mark all schedules with exceptions
                    if ($hasViolations) {
                        $scheduleData['agree_on_exception'] = true;
                        $scheduleData['exception_notes'] = $this->formatExceptionNotes($validationResult['violations']);
                    }
                    
                    // Clean schedule data (removes any embedded employee data)
                    $cleanScheduleData = $this->cleanScheduleData($scheduleData);
                    
                    $schedule = DailySchedule::create($cleanScheduleData);
                    
                    // Attach required skills if provided
                    if (isset($scheduleData['required_skills'])) {
                        $schedule->requiredSkills()->attach($scheduleData['required_skills']);
                    }
                    
                    $processedSchedules[] = $schedule->load(['empInfo', 'status', 'requiredSkills']);
                }
            } else {
                // Legacy individual schedule format
                // Retrieve employee data from database
                $employee = EmpInfo::with('skills')->find($dayData['emp_info_id']);
                if (!$employee) {
                    throw new \Exception("Employee with ID {$dayData['emp_info_id']} not found");
                }
                
                if ($hasViolations) {
                    $dayData['agree_on_exception'] = true;
                    $dayData['exception_notes'] = $this->formatExceptionNotes($validationResult['violations']);
                }
                
                $cleanDayData = $this->cleanScheduleData($dayData);
                $schedule = DailySchedule::create($cleanDayData);
                
                if (isset($dayData['required_skills'])) {
                    $schedule->requiredSkills()->attach($dayData['required_skills']);
                }
                
                $processedSchedules[] = $schedule->load(['empInfo', 'status', 'requiredSkills']);
            }
        }
        
        return [
            'schedules' => $processedSchedules,
            'validation_result' => $validationResult,
            'week_summary' => $this->generateWeekSummary($flatScheduleData, $validationResult)
        ];
    }
    
    /**
     * Normalize weekly schedule data to flat structure for validation
     * Converts new day-level format to individual schedule entries
     */
    private function normalizeWeeklyScheduleData(array $weeklyScheduleData): array
    {
        $flatSchedules = [];
        
        foreach ($weeklyScheduleData as $dayData) {
            if (isset($dayData['schedules'])) {
                // New day-level format
                foreach ($dayData['schedules'] as $scheduleData) {
                    $scheduleData['date_of_day'] = $dayData['date_of_day'];
                    $flatSchedules[] = $scheduleData;
                }
            } else {
                // Legacy individual schedule format
                $flatSchedules[] = $dayData;
            }
        }
        
        return $flatSchedules;
    }
    
    /**
     * Transform API format to database format
     */
    private function transformApiToDbFormat(array $weeklyScheduleData): array
    {
        return array_map(function ($schedule) {
            $transformed = [
                'date_of_day' => $schedule['date'],
                'scheduled_start_time' => $schedule['start_time'],
                'scheduled_end_time' => $schedule['end_time'],
                'status_id' => $schedule['status'] === 'scheduled' ? 1 : 2, // Assuming status mapping
                'emp_info_id' => $schedule['employee']['emp_info_id'],
                'employee' => $schedule['employee'],
                // Keep original fields for backward compatibility
                'date' => $schedule['date'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'status' => $schedule['status']
            ];
            
            // Only include required_skills if they are provided and not empty
            if (isset($schedule['required_skills']) && !empty($schedule['required_skills'])) {
                $transformed['required_skills'] = $schedule['required_skills'];
            }
            
            return $transformed;
        }, $weeklyScheduleData);
    }
    
    /**
     * Validate the complete weekly schedule
     */
    protected function validateWeeklySchedule(array $weeklyScheduleData): array
    {
        $violations = [];
        
        // 1. Validate weekly skill coverage across all days
        $skillValidation = $this->validateWeeklySkillCoverage($weeklyScheduleData);
        if (!$skillValidation['valid']) {
            $violations = array_merge($violations, $skillValidation['violations']);
        }
        
        // 2. Validate weekly hours constraints for all employees
        $hoursValidation = $this->validateWeeklyHoursConstraints($weeklyScheduleData);
        if (!$hoursValidation['valid']) {
            $violations = array_merge($violations, $hoursValidation['violations']);
        }
        
        // 3. Validate employee availability and conflicts
        $conflictValidation = $this->validateEmployeeConflicts($weeklyScheduleData);
        if (!$conflictValidation['valid']) {
            $violations = array_merge($violations, $conflictValidation['violations']);
        }
        
        // 4. Validate data integrity
        $integrityValidation = $this->validateDataIntegrity($weeklyScheduleData);
        if (!$integrityValidation['valid']) {
            $violations = array_merge($violations, $integrityValidation['violations']);
        }
        
        return [
            'valid' => empty($violations),
            'violations' => $violations,
            'skill_coverage' => $skillValidation['coverage'] ?? [],
            'hours_summary' => $hoursValidation['summary'] ?? [],
            'conflicts' => $conflictValidation['conflicts'] ?? []
        ];
    }
    
    /**
     * Validate skill coverage across the entire week
     */
    protected function validateWeeklySkillCoverage(array $weeklyScheduleData): array
    {
        $violations = [];
        $skillCoverage = [];
        
        // Group schedules by date to analyze daily skill coverage
        $schedulesByDate = collect($weeklyScheduleData)->groupBy('date_of_day');
        
        foreach ($schedulesByDate as $date => $daySchedules) {
            $requiredSkillsForDay = collect();
            $availableSkillsForDay = collect();
            
            foreach ($daySchedules as $schedule) {
                // Collect required skills for this day
                if (isset($schedule['required_skills'])) {
                    $requiredSkillsForDay = $requiredSkillsForDay->merge($schedule['required_skills']);
                }
                
                // Collect available skills from assigned employees
                if (isset($schedule['employee']['skills'])) {
                    $employeeSkillIds = collect($schedule['employee']['skills'])->map(function ($skill) {
                        return $skill['id'] ?? $skill['skill_id'] ?? null;
                    })->filter();
                    $availableSkillsForDay = $availableSkillsForDay->merge($employeeSkillIds);
                }
            }
            
            $requiredSkillsForDay = $requiredSkillsForDay->unique();
            $availableSkillsForDay = $availableSkillsForDay->unique();
            
            // Check if all required skills are covered
            $missingSkills = $requiredSkillsForDay->diff($availableSkillsForDay);
            
            $skillCoverage[$date] = [
                'required_skills' => $requiredSkillsForDay->toArray(),
                'available_skills' => $availableSkillsForDay->toArray(),
                'missing_skills' => $missingSkills->toArray(),
                'fully_covered' => $missingSkills->isEmpty()
            ];
            
            if ($missingSkills->isNotEmpty()) {
                $skillNames = Skill::whereIn('id', $missingSkills)->pluck('name')->toArray();
                $violations[] = "Date {$date}: Missing required skills - " . implode(', ', $skillNames);
            }
        }
        
        return [
            'valid' => empty($violations),
            'violations' => $violations,
            'coverage' => $skillCoverage
        ];
    }
    
    /**
     * Validate weekly hours constraints for all employees
     */
    protected function validateWeeklyHoursConstraints(array $weeklyScheduleData): array
    {
        $violations = [];
        $hoursSummary = [];
        
        // Group schedules by employee
        $schedulesByEmployee = collect($weeklyScheduleData)->groupBy('emp_info_id');
        
        foreach ($schedulesByEmployee as $empId => $employeeSchedules) {
            $employee = EmpInfo::with('schedulePreferences')->find($empId);
            
            if (!$employee) {
                continue;
            }
            
            // Default to 40 hours if no preferences set
            $maxWeeklyHours = 40;
            
            // First check if embedded employee data has max_weekly_hours
            $firstSchedule = $employeeSchedules->first();
            if (isset($firstSchedule['employee']['employment_info']['max_weekly_hours'])) {
                $maxWeeklyHours = $firstSchedule['employee']['employment_info']['max_weekly_hours'];
            } elseif ($employee->schedulePreferences && !$employee->schedulePreferences->isEmpty()) {
                $maxWeeklyHours = $employee->schedulePreferences->first()->maximum_hours;
            }
            
            $totalScheduledHours = 0;
            
            foreach ($employeeSchedules as $schedule) {
                $scheduledHours = $this->calculateScheduledHours(
                    $schedule['scheduled_start_time'],
                    $schedule['scheduled_end_time']
                );
                $totalScheduledHours += $scheduledHours;
            }
            
            $hoursSummary[$empId] = [
                'employee_name' => $employee->full_name,
                'total_scheduled_hours' => $totalScheduledHours,
                'max_weekly_hours' => $maxWeeklyHours,
                'hours_remaining' => max(0, $maxWeeklyHours - $totalScheduledHours),
                'is_over_limit' => $totalScheduledHours > $maxWeeklyHours
            ];
            
            if ($totalScheduledHours > $maxWeeklyHours) {
                $violations[] = "Employee {$employee->full_name}: Weekly hours limit exceeded. Scheduled: {$totalScheduledHours}h, Maximum: {$maxWeeklyHours}h";
            }
        }
        
        return [
            'valid' => empty($violations),
            'violations' => $violations,
            'summary' => $hoursSummary
        ];
    }
    
    /**
     * Validate employee scheduling conflicts
     */
    protected function validateEmployeeConflicts(array $weeklyScheduleData): array
    {
        $violations = [];
        $conflicts = [];
        
        // Group schedules by employee and date
        $schedulesByEmployeeAndDate = collect($weeklyScheduleData)
            ->groupBy('emp_info_id')
            ->map(function ($employeeSchedules) {
                return $employeeSchedules->groupBy('date_of_day');
            });
        
        foreach ($schedulesByEmployeeAndDate as $empId => $dateSchedules) {
            $employee = EmpInfo::find($empId);
            $employeeName = $employee ? $employee->full_name : 'Unknown Employee';
            
            foreach ($dateSchedules as $date => $daySchedules) {
                if ($daySchedules->count() > 1) {
                    // Multiple schedules for same employee on same day
                    $timeConflicts = $this->checkTimeConflicts($daySchedules->toArray());
                    
                    if (!empty($timeConflicts)) {
                        $conflicts[] = [
                            'employee_id' => $empId,
                            'employee_name' => $employeeName,
                            'date' => $date,
                            'type' => 'time_overlap',
                            'details' => $timeConflicts
                        ];
                        
                        $violations[] = "Employee {$employeeName} has overlapping schedules on {$date}";
                    }
                }
            }
        }
        
        return [
            'valid' => empty($violations),
            'violations' => $violations,
            'conflicts' => $conflicts
        ];
    }
    
    /**
     * Validate data integrity of the weekly schedule
     */
    protected function validateDataIntegrity(array $weeklyScheduleData): array
    {
        $violations = [];
        
        foreach ($weeklyScheduleData as $index => $schedule) {
            // Validate required fields
            $requiredFields = ['date_of_day', 'scheduled_start_time', 'scheduled_end_time', 'status_id', 'emp_info_id', 'employee'];
            
            foreach ($requiredFields as $field) {
                if (!isset($schedule[$field]) || empty($schedule[$field])) {
                    $violations[] = "Schedule entry {$index}: Missing required field '{$field}'";
                }
            }
            
            // Validate employee data integrity
            if (isset($schedule['employee'])) {
                if (!isset($schedule['employee']['id']) || $schedule['employee']['id'] != $schedule['emp_info_id']) {
                    $violations[] = "Schedule entry {$index}: Employee ID mismatch between emp_info_id and embedded employee data";
                }
                
                if (!isset($schedule['employee']['skills']) || !is_array($schedule['employee']['skills'])) {
                    $violations[] = "Schedule entry {$index}: Employee skills data is missing or invalid";
                }
            } else {
                $violations[] = "Schedule entry {$index}: Missing embedded employee data";
            }
            
            // Validate time format and logic
            if (isset($schedule['scheduled_start_time']) && isset($schedule['scheduled_end_time'])) {
                try {
                    $start = Carbon::createFromFormat('H:i:s', $schedule['scheduled_start_time']);
                    $end = Carbon::createFromFormat('H:i:s', $schedule['scheduled_end_time']);
                    
                    if ($end->lte($start)) {
                        $violations[] = "Schedule entry {$index}: End time must be after start time";
                    }
                } catch (\Exception $e) {
                    $violations[] = "Schedule entry {$index}: Invalid time format";
                }
            }
        }
        
        return [
            'valid' => empty($violations),
            'violations' => $violations
        ];
    }
    
    /**
     * Check for time conflicts in schedules for the same day
     */
    protected function checkTimeConflicts(array $daySchedules): array
    {
        $conflicts = [];
        
        for ($i = 0; $i < count($daySchedules); $i++) {
            for ($j = $i + 1; $j < count($daySchedules); $j++) {
                $schedule1 = $daySchedules[$i];
                $schedule2 = $daySchedules[$j];
                
                $start1 = Carbon::createFromFormat('H:i:s', $schedule1['scheduled_start_time']);
                $end1 = Carbon::createFromFormat('H:i:s', $schedule1['scheduled_end_time']);
                $start2 = Carbon::createFromFormat('H:i:s', $schedule2['scheduled_start_time']);
                $end2 = Carbon::createFromFormat('H:i:s', $schedule2['scheduled_end_time']);
                
                // Check for overlap
                if ($start1->lt($end2) && $start2->lt($end1)) {
                    $conflicts[] = [
                        'schedule_1' => [
                            'start' => $schedule1['scheduled_start_time'],
                            'end' => $schedule1['scheduled_end_time']
                        ],
                        'schedule_2' => [
                            'start' => $schedule2['scheduled_start_time'],
                            'end' => $schedule2['scheduled_end_time']
                        ]
                    ];
                }
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Calculate scheduled hours from start and end times
     */
    protected function calculateScheduledHours(string $startTime, string $endTime): float
    {
        $start = Carbon::createFromFormat('H:i:s', $startTime);
        $end = Carbon::createFromFormat('H:i:s', $endTime);
        
        return $end->diffInHours($start, true);
    }
    
    /**
     * Clean schedule data by removing embedded employee information
     */
    protected function cleanScheduleData(array $scheduleData): array
    {
        // Remove embedded employee data as it's not part of the database schema
        unset($scheduleData['employee']);
        
        // Calculate scheduled_hours if not provided
        if (!isset($scheduleData['scheduled_hours'])) {
            $scheduleData['scheduled_hours'] = $this->calculateScheduledHours(
                $scheduleData['scheduled_start_time'],
                $scheduleData['scheduled_end_time']
            );
        }
        
        return $scheduleData;
    }
    
    /**
     * Format exception notes from violations
     */
    protected function formatExceptionNotes(array $violations): string
    {
        return 'Weekly validation violations: ' . implode('; ', $violations);
    }
    
    /**
     * Generate a comprehensive week summary
     */
    protected function generateWeekSummary(array $weeklyScheduleData, array $validationResult): array
    {
        $dates = collect($weeklyScheduleData)->pluck('date_of_day')->unique()->sort();
        $employees = collect($weeklyScheduleData)->pluck('emp_info_id')->unique();
        
        // Calculate total hours
        $totalHours = 0;
        foreach ($weeklyScheduleData as $schedule) {
            if (isset($schedule['scheduled_start_time']) && isset($schedule['scheduled_end_time'])) {
                $start = Carbon::parse($schedule['scheduled_start_time']);
                $end = Carbon::parse($schedule['scheduled_end_time']);
                $totalHours += $start->diffInHours($end);
            }
        }
        
        return [
            'week_start' => $dates->first(),
            'week_end' => $dates->last(),
            'total_schedules' => count($weeklyScheduleData),
            'unique_employees' => $employees->count(),
            'employees_scheduled' => $employees->count(),
            'unique_dates' => $dates->count(),
            'total_hours' => $totalHours,
            'validation_status' => $validationResult['valid'] ? 'passed' : 'failed',
            'total_violations' => count($validationResult['violations']),
            'skill_coverage_summary' => $validationResult['skill_coverage'] ?? [],
            'hours_summary' => $validationResult['hours_summary'] ?? [],
            'conflicts_summary' => $validationResult['conflicts'] ?? []
        ];
    }
}