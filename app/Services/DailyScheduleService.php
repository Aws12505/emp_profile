<?php

namespace App\Services;

use App\Models\DailySchedule;
use App\Models\EmpInfo;
use App\Models\Skill;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailyScheduleService
{
    /**
     * Validate and create a daily schedule with business rules
     */
    public function createSchedule(array $data): array
    {
        // Retrieve employee data from database to validate existence
         $employee = EmpInfo::with('skills')->find($data['emp_info_id']);
         if (!$employee) {
             throw new \Exception("Employee with ID {$data['emp_info_id']} not found");
         }
        
        $validationResult = $this->validateBusinessRules($data);
        
        // If validation fails, set agree_on_exception to true
        if (!$validationResult['valid']) {
            $data['agree_on_exception'] = true;
            $data['exception_notes'] = $this->formatExceptionNotes($validationResult['violations']);
        }
        
        // Remove any embedded employee data before saving
        $cleanData = $this->cleanScheduleData($data);
        
        $schedule = DailySchedule::create($cleanData);
        
        // Attach required skills if provided
        if (isset($data['required_skills'])) {
            $schedule->requiredSkills()->attach($data['required_skills']);
        }
        
        return [
            'schedule' => $schedule->load(['empInfo', 'status', 'requiredSkills']),
            'validation_result' => $validationResult
        ];
    }
    
    /**
     * Create multiple schedules for a single day with comprehensive validation
     */
    public function createDaySchedules(string $date, array $schedulesData): array
    {
        $validationResult = $this->validateDaySchedules($date, $schedulesData);
        $createdSchedules = [];
        
        foreach ($schedulesData as $scheduleData) {
            // Add the date to each schedule
            $scheduleData['date_of_day'] = $date;
            
            // Retrieve employee data from database to validate existence
            $employee = EmpInfo::with('skills')->find($scheduleData['emp_info_id']);
            if (!$employee) {
                throw new \Exception("Employee with ID {$scheduleData['emp_info_id']} not found");
            }
            
            // If day-level validation fails, mark all schedules with exceptions
            if (!$validationResult['valid']) {
                $scheduleData['agree_on_exception'] = true;
                $scheduleData['exception_notes'] = $this->formatExceptionNotes($validationResult['violations']);
            }
            
            // Remove any embedded employee data before saving
            $cleanScheduleData = $this->cleanScheduleData($scheduleData);
            
            $schedule = DailySchedule::create($cleanScheduleData);
            
            // Attach required skills if provided
            if (isset($scheduleData['required_skills'])) {
                $schedule->requiredSkills()->attach($scheduleData['required_skills']);
            }
            
            $createdSchedules[] = $schedule->load(['empInfo', 'status', 'requiredSkills']);
        }
        
        return [
            'schedules' => $createdSchedules,
            'validation_result' => $validationResult,
            'day_summary' => $this->generateDaySummary($date, $createdSchedules)
        ];
    }
    
    /**
     * Update a daily schedule with business rules validation
     */
    public function updateSchedule(DailySchedule $schedule, array $data): array
    {
        $validationResult = $this->validateBusinessRules($data, $schedule);
        
        // If validation fails, set agree_on_exception to true
        if (!$validationResult['valid']) {
            $data['agree_on_exception'] = true;
            $data['exception_notes'] = $this->formatExceptionNotes($validationResult['violations']);
        } else {
            // If validation passes, ensure agree_on_exception is false or not set
            $data['agree_on_exception'] = false;
            $data['exception_notes'] = null;
        }
        
        $schedule->update($data);
        
        // Sync required skills if provided
        if (isset($data['required_skills'])) {
            $schedule->requiredSkills()->sync($data['required_skills']);
        }
        
        // Load relationships conditionally
        $relationships = ['empInfo', 'status'];
        if ($schedule->requiredSkills()->exists()) {
            $relationships[] = 'requiredSkills';
        }
        
        return [
            'schedule' => $schedule->load($relationships),
            'validation_result' => $validationResult
        ];
    }
    
    /**
     * Validate business rules for scheduling
     */
    public function validateBusinessRules(array $data, ?DailySchedule $existingSchedule = null): array
    {
        $violations = [];
        
        // 1. Validate skill requirements
        $skillValidation = $this->validateSkillRequirements($data);
        if (!$skillValidation['valid']) {
            $violations = array_merge($violations, $skillValidation['violations']);
        }
        
        // 2. Validate weekly hours constraint
        $hoursValidation = $this->validateWeeklyHours($data, $existingSchedule);
        if (!$hoursValidation['valid']) {
            $violations = array_merge($violations, $hoursValidation['violations']);
        }
        
        return [
            'valid' => empty($violations),
            'violations' => $violations
        ];
    }
    
    /**
     * Validate skill requirements
     */
    protected function validateSkillRequirements(array $data): array
    {
        if (!isset($data['required_skills']) || empty($data['required_skills'])) {
            return ['valid' => true, 'violations' => []];
        }
        
        $requiredSkills = collect($data['required_skills']);
        $employee = EmpInfo::with('skills')->find($data['emp_info_id']);
        
        if (!$employee) {
            return [
                'valid' => false,
                'violations' => ['Employee not found']
            ];
        }
        
        $employeeSkills = $employee->skills->pluck('id');
        $missingSkills = $requiredSkills->diff($employeeSkills);
        
        if ($missingSkills->isNotEmpty()) {
            $skillNames = Skill::whereIn('id', $missingSkills)->pluck('name')->toArray();
            return [
                'valid' => false,
                'violations' => [
                    'Employee does not possess required skills: ' . implode(', ', $skillNames)
                ]
            ];
        }
        
        return ['valid' => true, 'violations' => []];
    }
    
    /**
     * Validate weekly hours constraint
     */
    protected function validateWeeklyHours(array $data, ?DailySchedule $existingSchedule = null): array
    {
        $employee = EmpInfo::with('schedulePreferences')->find($data['emp_info_id']);
        
        if (!$employee || $employee->schedulePreferences->isEmpty()) {
            return ['valid' => true, 'violations' => []];
        }
        
        // Get the maximum weekly hours from preferences
        $maxWeeklyHours = $employee->schedulePreferences->first()->maximum_hours;
        
        // Calculate scheduled hours for this entry
        $scheduledHours = $this->calculateScheduledHours(
            $data['scheduled_start_time'],
            $data['scheduled_end_time']
        );
        
        // Get work week boundaries
        $weekStart = DailySchedule::getWorkWeekStart($data['date_of_day']);
        $weekEnd = DailySchedule::getWorkWeekEnd($data['date_of_day']);
        
        // Get existing schedules for this week (excluding current if updating)
        $weeklySchedulesQuery = DailySchedule::where('emp_info_id', $data['emp_info_id'])
            ->whereBetween('date_of_day', [$weekStart, $weekEnd]);
            
        if ($existingSchedule) {
            $weeklySchedulesQuery->where('id', '!=', $existingSchedule->id);
        }
        
        $weeklySchedules = $weeklySchedulesQuery->get();
        
        // Calculate total weekly hours
        $totalWeeklyHours = $weeklySchedules->sum('scheduled_hours') + $scheduledHours;
        
        if ($totalWeeklyHours > $maxWeeklyHours) {
            return [
                'valid' => false,
                'violations' => [
                    "Weekly hours limit exceeded. Total: {$totalWeeklyHours}h, Maximum: {$maxWeeklyHours}h"
                ]
            ];
        }
        
        return ['valid' => true, 'violations' => []];
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
     * Format exception notes from violations
     */
    protected function formatExceptionNotes(array $violations): string
    {
        return 'Business rule violations: ' . implode('; ', $violations);
    }
    
    /**
     * Get weekly schedule summary for an employee
     */
    public function getWeeklyScheduleSummary(string $date, int $empInfoId): array
    {
        $weekStart = DailySchedule::getWorkWeekStart($date);
        $weekEnd = DailySchedule::getWorkWeekEnd($date);
        
        $schedules = DailySchedule::with(['empInfo', 'status', 'requiredSkills'])
            ->where('emp_info_id', $empInfoId)
            ->whereBetween('date_of_day', [$weekStart, $weekEnd])
            ->orderBy('date_of_day')
            ->get();
            
        $employee = EmpInfo::with('schedulePreferences')->find($empInfoId);
        $maxWeeklyHours = $employee->schedulePreferences->first()->maximum_hours ?? 0;
        
        $totalScheduledHours = $schedules->sum('scheduled_hours');
        $totalActualHours = $schedules->sum('actual_hours');
        
        return [
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'employee' => $employee,
            'schedules' => $schedules,
            'total_scheduled_hours' => $totalScheduledHours,
            'total_actual_hours' => $totalActualHours,
            'max_weekly_hours' => $maxWeeklyHours,
            'hours_remaining' => max(0, $maxWeeklyHours - $totalScheduledHours),
            'is_over_limit' => $totalScheduledHours > $maxWeeklyHours
        ];
    }
    
    /**
     * Check if all required skills are covered by scheduled employees for a date
     */
    public function validateDaySkillCoverage(string $date, array $requiredSkills): array
    {
        $daySchedules = DailySchedule::with(['empInfo.skills', 'requiredSkills'])
            ->where('date_of_day', $date)
            ->get();
            
        $allEmployeeSkills = collect();
        
        foreach ($daySchedules as $schedule) {
            $employeeSkills = $schedule->empInfo->skills->pluck('id');
            $allEmployeeSkills = $allEmployeeSkills->merge($employeeSkills);
        }
        
        $allEmployeeSkills = $allEmployeeSkills->unique();
        $requiredSkillsCollection = collect($requiredSkills);
        $missingSkills = $requiredSkillsCollection->diff($allEmployeeSkills);
        
        return [
            'all_skills_covered' => $missingSkills->isEmpty(),
            'covered_skills' => $allEmployeeSkills->intersect($requiredSkillsCollection)->values()->toArray(),
            'missing_skills' => $missingSkills->values()->toArray()
        ];
    }
    
    /**
     * Validate day-level schedules with split shift support and overlap prevention
     */
    protected function validateDaySchedules(string $date, array $schedulesData): array
    {
        $violations = [];
        
        // 1. Check for time overlaps for the same employee
        $overlapViolations = $this->validateTimeOverlaps($schedulesData);
        if (!empty($overlapViolations)) {
            $violations = array_merge($violations, $overlapViolations);
        }
        
        // 2. Validate skill requirements for each schedule
        foreach ($schedulesData as $index => $scheduleData) {
            $scheduleData['date_of_day'] = $date;
            $skillValidation = $this->validateSkillRequirements($scheduleData);
            if (!$skillValidation['valid']) {
                foreach ($skillValidation['violations'] as $violation) {
                    $violations[] = "Schedule #{$index}: {$violation}";
                }
            }
        }
        
        // 3. Validate weekly hours constraints for each employee
        $employeeHours = [];
        foreach ($schedulesData as $index => $scheduleData) {
            $empId = $scheduleData['emp_info_id'];
            $hours = $this->calculateScheduledHours(
                $scheduleData['scheduled_start_time'],
                $scheduleData['scheduled_end_time']
            );
            
            if (!isset($employeeHours[$empId])) {
                $employeeHours[$empId] = 0;
            }
            $employeeHours[$empId] += $hours;
        }
        
        foreach ($employeeHours as $empId => $dayHours) {
            $scheduleData = ['emp_info_id' => $empId, 'date_of_day' => $date];
            $hoursValidation = $this->validateWeeklyHoursForEmployee($empId, $date, $dayHours);
            if (!$hoursValidation['valid']) {
                $violations = array_merge($violations, $hoursValidation['violations']);
            }
        }
        
        return [
            'valid' => empty($violations),
            'violations' => $violations
        ];
    }
    
    /**
     * Check for time overlaps within the same employee's schedules
     */
    protected function validateTimeOverlaps(array $schedulesData): array
    {
        $violations = [];
        $employeeSchedules = [];
        
        // Group schedules by employee
        foreach ($schedulesData as $index => $schedule) {
            $empId = $schedule['emp_info_id'];
            if (!isset($employeeSchedules[$empId])) {
                $employeeSchedules[$empId] = [];
            }
            $employeeSchedules[$empId][] = array_merge($schedule, ['index' => $index]);
        }
        
        // Check for overlaps within each employee's schedules
        foreach ($employeeSchedules as $empId => $schedules) {
            if (count($schedules) > 1) {
                for ($i = 0; $i < count($schedules); $i++) {
                    for ($j = $i + 1; $j < count($schedules); $j++) {
                        $schedule1 = $schedules[$i];
                        $schedule2 = $schedules[$j];
                        
                        $start1 = Carbon::createFromFormat('H:i:s', $schedule1['scheduled_start_time']);
                        $end1 = Carbon::createFromFormat('H:i:s', $schedule1['scheduled_end_time']);
                        $start2 = Carbon::createFromFormat('H:i:s', $schedule2['scheduled_start_time']);
                        $end2 = Carbon::createFromFormat('H:i:s', $schedule2['scheduled_end_time']);
                        
                        // Check for overlap: start1 < end2 && start2 < end1
                        if ($start1->lt($end2) && $start2->lt($end1)) {
                            $violations[] = "Employee ID {$empId} has overlapping shifts: Schedule #{$schedule1['index']} ({$schedule1['scheduled_start_time']}-{$schedule1['scheduled_end_time']}) overlaps with Schedule #{$schedule2['index']} ({$schedule2['scheduled_start_time']}-{$schedule2['scheduled_end_time']})";
                        }
                    }
                }
            }
        }
        
        return $violations;
    }
    
    /**
     * Validate weekly hours for a specific employee with additional day hours
     */
    protected function validateWeeklyHoursForEmployee(int $empId, string $date, float $additionalHours): array
    {
        $employee = EmpInfo::with('schedulePreferences')->find($empId);
        
        if (!$employee || $employee->schedulePreferences->isEmpty()) {
            return ['valid' => true, 'violations' => []];
        }
        
        // Get the maximum weekly hours from preferences
        $maxWeeklyHours = $employee->schedulePreferences->first()->maximum_hours;
        
        // Get work week boundaries
        $weekStart = DailySchedule::getWorkWeekStart($date);
        $weekEnd = DailySchedule::getWorkWeekEnd($date);
        
        // Get existing schedules for this week
        $weeklySchedules = DailySchedule::where('emp_info_id', $empId)
            ->whereBetween('date_of_day', [$weekStart, $weekEnd])
            ->where('date_of_day', '!=', $date) // Exclude current date
            ->get();
        
        // Calculate total weekly hours including the new day's hours
        $existingWeeklyHours = $weeklySchedules->sum('scheduled_hours');
        $totalWeeklyHours = $existingWeeklyHours + $additionalHours;
        
        if ($totalWeeklyHours > $maxWeeklyHours) {
            return [
                'valid' => false,
                'violations' => [
                    "Employee ID {$empId}: Weekly hours limit exceeded. Total: {$totalWeeklyHours}h, Maximum: {$maxWeeklyHours}h"
                ]
            ];
        }
        
        return ['valid' => true, 'violations' => []];
    }
    
    /**
     * Generate summary for a day's schedules
     */
    protected function generateDaySummary(string $date, array $schedules): array
    {
        $employeeIds = collect($schedules)->pluck('emp_info_id')->unique();
        $totalHours = collect($schedules)->sum('scheduled_hours');
        $schedulesWithExceptions = collect($schedules)->where('agree_on_exception', true)->count();
        
        // Collect all required skills
        $allRequiredSkills = collect($schedules)
            ->flatMap(function ($schedule) {
                return $schedule->requiredSkills->pluck('id');
            })
            ->unique();
            
        // Collect all available skills from employees
        $allAvailableSkills = collect($schedules)
            ->flatMap(function ($schedule) {
                return $schedule->empInfo->skills->pluck('id');
            })
            ->unique();
        
        return [
            'date' => $date,
            'total_schedules' => count($schedules),
            'unique_employees' => $employeeIds->count(),
            'total_hours' => $totalHours,
            'schedules_with_exceptions' => $schedulesWithExceptions,
            'required_skills' => $allRequiredSkills->toArray(),
            'available_skills' => $allAvailableSkills->toArray(),
            'skill_coverage_complete' => $allRequiredSkills->diff($allAvailableSkills)->isEmpty()
        ];
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
}