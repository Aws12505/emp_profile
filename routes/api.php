<?php

use App\Http\Controllers\Api\DailyScheduleController;
use App\Http\Controllers\Api\EmpEmploymentInfoController;
use App\Http\Controllers\Api\EmpInfoController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\SchedulePreferenceController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.verify')->group(function () {
Route::apiResource('stores', StoreController::class);
Route::apiResource('employees', EmpInfoController::class);
Route::apiResource('employment-info', EmpEmploymentInfoController::class);
Route::apiResource('positions', PositionController::class);
Route::apiResource('skills', SkillController::class);
Route::apiResource('preferences', PreferenceController::class);
Route::apiResource('schedule-preferences', SchedulePreferenceController::class);
Route::apiResource('statuses', StatusController::class);
Route::apiResource('daily-schedules', DailyScheduleController::class);
// Additional routes for employee skills
Route::post('employees/{employee}/skills/{skill}', [EmpInfoController::class, 'attachSkill']);
Route::delete('employees/{employee}/skills/{skill}', [EmpInfoController::class, 'detachSkill']);
Route::put('employees/{employee}/skills/{skill}', [EmpInfoController::class, 'updateSkillRating']);

Route::get('stores/{storeId}/employees', [EmpInfoController::class, 'getUsersByStoreId']);

// Additional routes for daily schedule skills
Route::post('daily-schedules/{dailySchedule}/skills/{skill}', [DailyScheduleController::class, 'attachSkill']);
Route::delete('daily-schedules/{dailySchedule}/skills/{skill}', [DailyScheduleController::class, 'detachSkill']);
Route::get('daily-schedules/weekly/{empInfoId}', [DailyScheduleController::class, 'getWeeklySchedule']);

// Weekly schedule processing routes
Route::post('weekly-schedules/process', [DailyScheduleController::class, 'processWeeklySchedule']);
Route::get('weekly-schedules/analysis', [DailyScheduleController::class, 'getWeeklyScheduleAnalysis']);
});