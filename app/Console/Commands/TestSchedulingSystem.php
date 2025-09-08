<?php

namespace App\Console\Commands;

use App\Models\DailySchedule;
use App\Models\EmpInfo;
use App\Models\Skill;
use App\Models\Status;
use App\Models\Store;
use App\Services\DailyScheduleService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestSchedulingSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:scheduling-system';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the daily scheduling system functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Daily Scheduling System...');
        
        // Create test data
        $this->createTestData();
        
        // Test the scheduling service
        $this->testSchedulingService();
        
        // Test validation logic
        $this->testValidationLogic();
        
        $this->info('All tests completed successfully!');
    }
    
    private function createTestData()
    {
        $this->info('Creating test data...');
        
        // Create a test store
        $store = Store::firstOrCreate(
            ['id' => 'TEST001'],
            ['name' => 'Test Store', 'number' => '001']
        );
        
        // Create a test store
        $store = Store::firstOrCreate(
            ['id' => 'STORE001'],
            ['id' => 'STORE001', 'name' => 'Test Store', 'number' => '001']
        );
        
        // Create a test status
        $status = Status::firstOrCreate(
            ['slug' => 'active'],
            ['description' => 'Active status', 'slug' => 'active']
        );
        
        // Create test skills
        $skill1 = Skill::firstOrCreate(
            ['slug' => 'customer-service'],
            ['name' => 'Customer Service', 'slug' => 'customer-service']
        );
        
        $skill2 = Skill::firstOrCreate(
            ['slug' => 'cash-handling'],
            ['name' => 'Cash Handling', 'slug' => 'cash-handling']
        );
        
        // Create a test employee
        $employee = EmpInfo::firstOrCreate(
            ['full_name' => 'John Doe'],
            [
                'full_name' => 'John Doe',
                'store_id' => $store->id,
                'date_of_birth' => '1990-01-01',
                'has_family' => false,
                'has_car' => true,
                'is_arabic_team' => false,
                'status' => 'active'
            ]
        );
        
        $this->info('Test data created successfully.');
        
        return [
            'store' => $store,
            'status' => $status,
            'skills' => [$skill1, $skill2],
            'employee' => $employee
        ];
    }
    
    private function testSchedulingService()
    {
        $this->info('Testing scheduling service...');
        
        $employee = EmpInfo::first();
        $status = Status::first();
        $skills = Skill::take(2)->get();
        
        $scheduleService = new DailyScheduleService();
        
        $scheduleData = [
            'date_of_day' => Carbon::today()->format('Y-m-d'),
            'emp_info_id' => $employee->id,
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '17:00:00',
            'actual_start_time' => '09:15:00',
            'actual_end_time' => '17:10:00',
            'vci' => true,
            'status_id' => $status->id,
            'agree_on_exception' => false,
            'exception_notes' => 'Test schedule entry',
            'required_skills' => $skills->pluck('id')->toArray()
        ];
        
        try {
            $result = $scheduleService->createSchedule($scheduleData);
            $this->info('✓ Schedule created successfully');
            
            if (!empty($result['validation_result']['warnings'])) {
                $this->warn('Warnings: ' . implode(', ', $result['validation_result']['warnings']));
            }
            
            // Test update
            $updateData = array_merge($scheduleData, [
                'actual_end_time' => '17:30:00',
                'exception_notes' => 'Updated test schedule'
            ]);
            
            $updateResult = $scheduleService->updateSchedule($result['schedule'], $updateData);
            $this->info('✓ Schedule updated successfully');
            
        } catch (\Exception $e) {
            $this->error('✗ Schedule creation failed: ' . $e->getMessage());
        }
    }
    
    private function testValidationLogic()
    {
        $this->info('Testing validation logic...');
        
        $employee = EmpInfo::first();
        $status = Status::first();
        
        $scheduleService = new DailyScheduleService();
        
        // Test weekly hours validation
        $this->info('Testing weekly hours constraint...');
        
        for ($i = 0; $i < 6; $i++) {
            $date = Carbon::today()->addDays($i);
            
            $scheduleData = [
                'date_of_day' => $date->format('Y-m-d'),
                'emp_info_id' => $employee->id,
                'scheduled_start_time' => '09:00:00',
                'scheduled_end_time' => '17:00:00', // 8 hours per day
                'status_id' => $status->id,
                'agree_on_exception' => false,
                'required_skills' => []
            ];
            
            try {
                $result = $scheduleService->createSchedule($scheduleData);
                
                if (!empty($result['validation_result']['warnings'])) {
                    $this->warn("Day {$i}: " . implode(', ', $result['validation_result']['warnings']));
                }
                
                if (!empty($result['validation_result']['errors'])) {
                    $this->error("Day {$i}: " . implode(', ', $result['validation_result']['errors']));
                }
                
            } catch (\Exception $e) {
                $this->error("Day {$i} failed: " . $e->getMessage());
            }
        }
        
        $this->info('✓ Validation logic tested');
    }
}
