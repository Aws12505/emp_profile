<?php

namespace Database\Seeders;

use App\Models\DailySchedule;
use App\Models\EmpInfo;
use App\Models\Skill;
use App\Models\Status;
use App\Services\DailyScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DailyScheduleSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Get existing data
        $employees = EmpInfo::all();
        $skills = Skill::all();
        $status = Status::first();
        
        if ($employees->isEmpty() || $skills->isEmpty() || !$status) {
            $this->command->info('Please run other seeders first to create employees, skills, and statuses.');
            return;
        }
        
        $scheduleService = new DailyScheduleService();
        
        // Create schedules for the current week
        $startOfWeek = Carbon::now()->startOfWeek();
        
        foreach ($employees->take(3) as $employee) {
            for ($day = 0; $day < 7; $day++) {
                $date = $startOfWeek->copy()->addDays($day);
                
                // Skip weekends for some variety
                if ($day == 0 || $day == 6) continue;
                
                $scheduleData = [
                    'date_of_day' => $date->format('Y-m-d'),
                    'emp_info_id' => $employee->id,
                    'scheduled_start_time' => '09:00:00',
                    'scheduled_end_time' => '17:00:00',
                    'actual_start_time' => $day < 3 ? '09:15:00' : null, // Some actual times
                    'actual_end_time' => $day < 3 ? '17:10:00' : null,
                    'vci' => $day % 2 == 0,
                    'status_id' => $status->id,
                    'agree_on_exception' => false,
                    'exception_notes' => $day == 2 ? 'Late due to traffic' : null,
                    'required_skills' => $skills->random(2)->pluck('id')->toArray()
                ];
                
                try {
                    $result = $scheduleService->createSchedule($scheduleData);
                    $this->command->info("Created schedule for {$employee->first_name} on {$date->format('Y-m-d')}");
                    
                    if (!empty($result['validation_result']['warnings'])) {
                        $this->command->warn('Warnings: ' . implode(', ', $result['validation_result']['warnings']));
                    }
                } catch (\Exception $e) {
                    $this->command->error("Failed to create schedule: {$e->getMessage()}");
                }
            }
        }
        
        $this->command->info('Daily schedule seeding completed!');
    }
}
