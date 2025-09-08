<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyScheduleRequest;
use App\Http\Requests\WeeklyScheduleRequest;
use App\Models\DailySchedule;
use App\Models\Skill;
use App\Services\DailyScheduleService;
use App\Services\WeeklyScheduleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DailyScheduleController extends Controller
{
    protected $dailyScheduleService;
    protected $weeklyScheduleService;

    public function __construct(
        DailyScheduleService $dailyScheduleService,
        WeeklyScheduleService $weeklyScheduleService
    ) {
        $this->dailyScheduleService = $dailyScheduleService;
        $this->weeklyScheduleService = $weeklyScheduleService;
    }
    public function index(Request $request)
    {
        $query = DailySchedule::with(['empInfo', 'status', 'requiredSkills']);
        
        // Filter by date range if provided
        if ($request->has('start_date')) {
            $query->where('date_of_day', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->where('date_of_day', '<=', $request->end_date);
        }
        
        // Filter by employee if provided
        if ($request->has('emp_info_id')) {
            $query->where('emp_info_id', $request->emp_info_id);
        }
        
        return $query->orderBy('date_of_day')->get();
    }

    public function store(DailyScheduleRequest $request)
    {
        $validated = $request->validated();
        
        // Check if this is a day-level array request
        if (isset($validated['schedules'])) {
            return $this->storeDaySchedules($validated);
        }
        
        // Individual schedule creation (backward compatibility)
        $result = $this->dailyScheduleService->createSchedule($validated);
        
        return response()->json([
            'data' => $result['schedule'],
            'validation_result' => $result['validation_result']
        ], 201);
    }
    
    /**
     * Store multiple schedules for a single day
     */
    public function storeDaySchedules(array $validated): \Illuminate\Http\JsonResponse
    {
        $result = $this->dailyScheduleService->createDaySchedules(
            $validated['date_of_day'],
            $validated['schedules']
        );
        
        return response()->json([
            'data' => $result['schedules'],
            'validation_result' => $result['validation_result'],
            'day_summary' => $result['day_summary']
        ], 201);
    }

    public function show(DailySchedule $dailySchedule)
    {
        return $dailySchedule->load(['empInfo', 'status', 'requiredSkills']);
    }

    public function update(DailyScheduleRequest $request, DailySchedule $dailySchedule)
    {
        $result = $this->dailyScheduleService->updateSchedule($dailySchedule, $request->validated());
        
        return response()->json([
            'data' => $result['schedule'],
            'validation_result' => $result['validation_result']
        ]);
    }

    public function destroy(DailySchedule $dailySchedule)
    {
        $dailySchedule->delete();
        return response()->noContent();
    }

    public function attachSkill(Request $request, DailySchedule $dailySchedule, Skill $skill)
    {
        $validated = $request->validate([
            'is_required' => 'boolean'
        ]);

        $dailySchedule->requiredSkills()->attach($skill->id, $validated);
        return response()->noContent();
    }

    public function detachSkill(DailySchedule $dailySchedule, Skill $skill)
    {
        $dailySchedule->requiredSkills()->detach($skill->id);
        return response()->noContent();
    }

    public function getWeeklySchedule(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'emp_info_id' => 'nullable|exists:emp_infos,id'
        ]);

        $weekStart = DailySchedule::getWorkWeekStart($validated['date']);
        $weekEnd = DailySchedule::getWorkWeekEnd($validated['date']);

        $query = DailySchedule::with(['empInfo', 'status', 'requiredSkills'])
            ->whereBetween('date_of_day', [$weekStart, $weekEnd]);

        if (isset($validated['emp_info_id'])) {
            $query->where('emp_info_id', $validated['emp_info_id']);
        }

        return $query->orderBy('date_of_day')->get();
    }

    /**
     * Process and validate a complete weekly schedule
     * 
     * Expected input structure:
     * {
     *     "weekly_schedule": [
     *         {
     *             "date_of_day": "2024-01-15",
     *             "emp_info_id": 1,
     *             "employee": {
     *                 "id": 1,
     *                 "full_name": "John Doe",
     *                 "skills": [
     *                     {"id": 1, "name": "PHP", "slug": "php"},
     *                     {"id": 2, "name": "Laravel", "slug": "laravel"}
     *                 ]
     *             },
     *             "scheduled_start_time": "09:00:00",
     *             "scheduled_end_time": "17:00:00",
     *             "actual_start_time": null,
     *             "actual_end_time": null,
     *             "vci": false,
     *             "status_id": 1,
     *             "required_skills": [1, 2],
     *             "agree_on_exception": false,
     *             "exception_notes": null
     *         }
     *         // ... more daily schedules for the week
     *     ]
     * }
     * 
     * @param WeeklyScheduleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processWeeklySchedule(WeeklyScheduleRequest $request)
    {
        $validated = $request->validated();
        
        $result = $this->weeklyScheduleService->validateAndProcessWeeklySchedule(
            $validated['weekly_schedule']
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Weekly schedule processed successfully',
            'data' => [
                'schedules' => $result['schedules'],
                'week_summary' => $result['week_summary']
            ],
            'validation_result' => $result['validation_result']
        ], 201);
    }

    /**
     * Get comprehensive weekly schedule analysis
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWeeklyScheduleAnalysis(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'emp_info_ids' => 'nullable|array',
            'emp_info_ids.*' => 'exists:emp_infos,id'
        ]);

        $weekStart = DailySchedule::getWorkWeekStart($validated['date']);
        $weekEnd = DailySchedule::getWorkWeekEnd($validated['date']);

        $query = DailySchedule::with(['empInfo.skills', 'status', 'requiredSkills'])
            ->whereBetween('date_of_day', [$weekStart, $weekEnd]);

        if (isset($validated['emp_info_ids'])) {
            $query->whereIn('emp_info_id', $validated['emp_info_ids']);
        }

        $schedules = $query->orderBy('date_of_day')->orderBy('scheduled_start_time')->get();
        
        // Group schedules by date for analysis
        $schedulesByDate = $schedules->groupBy('date_of_day');
        $analysis = [];
        
        foreach ($schedulesByDate as $date => $daySchedules) {
            $requiredSkills = $daySchedules->flatMap->requiredSkills->pluck('id')->unique();
            $availableSkills = $daySchedules->flatMap(function ($schedule) {
                return $schedule->empInfo->skills->pluck('id');
            })->unique();
            
            $analysis[$date] = [
                'date' => $date,
                'total_schedules' => $daySchedules->count(),
                'total_employees' => $daySchedules->pluck('emp_info_id')->unique()->count(),
                'total_hours' => $daySchedules->sum('scheduled_hours'),
                'required_skills' => $requiredSkills->toArray(),
                'available_skills' => $availableSkills->toArray(),
                'missing_skills' => $requiredSkills->diff($availableSkills)->toArray(),
                'skill_coverage_complete' => $requiredSkills->diff($availableSkills)->isEmpty(),
                'schedules' => $daySchedules->values()
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'total_schedules' => $schedules->count(),
                'unique_employees' => $schedules->pluck('emp_info_id')->unique()->count(),
                'daily_analysis' => $analysis,
                'week_totals' => [
                    'total_hours' => $schedules->sum('scheduled_hours'),
                    'total_actual_hours' => $schedules->sum('actual_hours'),
                    'schedules_with_exceptions' => $schedules->where('agree_on_exception', true)->count()
                ]
            ]
        ]);
    }
}