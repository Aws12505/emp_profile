<?php

namespace App\Console\Commands;

use App\Models\DailySchedule;
use App\Models\EmpInfo;
use App\Models\Store;
use App\Services\WeeklyScheduleService;
use App\Http\Requests\WeeklyScheduleRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TestWeeklyScheduleSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:weekly-schedule-system';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the enhanced weekly schedule validation system';

    protected WeeklyScheduleService $weeklyScheduleService;

    public function __construct(WeeklyScheduleService $weeklyScheduleService)
    {
        parent::__construct();
        $this->weeklyScheduleService = $weeklyScheduleService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Enhanced Weekly Schedule Validation System');
        $this->info('=' . str_repeat('=', 50));

        // Test 1: Valid weekly schedule
        $this->info('\n1. Testing valid weekly schedule...');
        $this->testValidWeeklySchedule();

        // Test 2: Hours violation
        $this->info('\n2. Testing weekly hours violation...');
        $this->testHoursViolation();

        // Test 3: Skill coverage validation
        $this->info('\n3. Testing skill coverage validation...');
        $this->testSkillCoverage();

        // Test 4: Employee conflicts
        $this->info('\n4. Testing employee scheduling conflicts...');
        $this->testEmployeeConflicts();

        // Test 5: Data integrity validation
        $this->info('\n5. Testing data integrity validation...');
        $this->testDataIntegrity();

        $this->info('\n' . str_repeat('=', 60));
        $this->info('Weekly Schedule Validation System Test Complete!');
    }

    private function testValidWeeklySchedule()
    {
        $weeklyScheduleData = [
            [
                'date_of_day' => Carbon::now()->startOfWeek()->format('Y-m-d'),
                'scheduled_start_time' => '09:00:00',
                'scheduled_end_time' => '17:00:00',
                'status_id' => 1,
                'emp_info_id' => 1,
                'employee' => [
                    'emp_info_id' => 1,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@test.com',
                    'skills' => [
                        ['skill_id' => 1, 'skill_name' => 'Customer Service', 'rating' => 4],
                        ['skill_id' => 2, 'skill_name' => 'Cash Handling', 'rating' => 5]
                    ],
                    'employment_info' => [
                        'position_id' => 1,
                        'store_id' => 1,
                        'max_weekly_hours' => 40,
                        'status' => 'active'
                    ]
                ],
                'required_skills' => [1, 2]
            ],
            [
                'date_of_day' => Carbon::now()->startOfWeek()->addDay()->format('Y-m-d'),
                'scheduled_start_time' => '10:00:00',
                'scheduled_end_time' => '18:00:00',
                'status_id' => 1,
                'emp_info_id' => 1,
                'employee' => [
                    'emp_info_id' => 1,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@test.com',
                    'skills' => [
                        ['skill_id' => 1, 'skill_name' => 'Customer Service', 'rating' => 5],
                        ['skill_id' => 3, 'skill_name' => 'Inventory Management', 'rating' => 4]
                    ],
                    'employment_info' => [
                        'position_id' => 1,
                        'store_id' => 1,
                        'max_weekly_hours' => 40,
                        'status' => 'active'
                    ]
                ],
                'required_skills' => [1, 2]
            ]
        ];

        try {
            $result = $this->weeklyScheduleService->validateAndProcessWeeklySchedule($weeklyScheduleData);
            $this->info('   ✓ Valid weekly schedule processed successfully');
            $this->info('   - Processed schedules: ' . count($weeklyScheduleData));
            $this->info('   - Total hours: ' . $result['week_summary']['total_hours']);
            $this->info('   - Employees scheduled: ' . $result['week_summary']['employees_scheduled']);
        } catch (\Exception $e) {
            $this->error('   ✗ Unexpected error: ' . $e->getMessage());
        }
    }

    private function testHoursViolation()
    {
        $weeklyScheduleData = [
            [
                'date_of_day' => Carbon::now()->startOfWeek()->format('Y-m-d'),
                'scheduled_start_time' => '08:00:00',
                'scheduled_end_time' => '20:00:00', // 12 hours
                'status_id' => 1,
                'emp_info_id' => 1,
                'employee' => [
                    'emp_info_id' => 1,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@test.com',
                    'skills' => [
                        ['skill_id' => 1, 'skill_name' => 'Customer Service', 'rating' => 4]
                    ],
                    'employment_info' => [
                        'position_id' => 1,
                        'store_id' => 1,
                        'max_weekly_hours' => 20, // Low limit to trigger violation
                        'status' => 'active'
                    ]
                ],
                'required_skills' => [1]
            ],
            [
                'date_of_day' => Carbon::now()->startOfWeek()->addDay()->format('Y-m-d'),
                'scheduled_start_time' => '08:00:00',
                'scheduled_end_time' => '20:00:00', // Another 12 hours (total 24 > 20 limit)
                'status_id' => 1,
                'emp_info_id' => 1,
                'employee' => [
                    'emp_info_id' => 1, // Same employee
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@test.com',
                    'skills' => [
                        ['skill_id' => 1, 'skill_name' => 'Customer Service', 'rating' => 4]
                    ],
                    'employment_info' => [
                        'position_id' => 1,
                        'store_id' => 1,
                        'max_weekly_hours' => 20,
                        'status' => 'active'
                    ]
                ],
                'required_skills' => [1]
            ]
        ];

        try {
            $result = $this->weeklyScheduleService->validateAndProcessWeeklySchedule($weeklyScheduleData);
            if (!$result['validation_result']['valid'] && !empty($result['validation_result']['violations'])) {
                $this->info('   ✓ Hours violation detected correctly');
                foreach ($result['validation_result']['violations'] as $violation) {
                    $this->info('   - ' . $violation);
                }
            } else {
                $this->error('   ✗ Hours violation not detected');
                $this->info('   - Debug: Valid=' . ($result['validation_result']['valid'] ? 'true' : 'false'));
                $this->info('   - Debug: Violations count=' . count($result['validation_result']['violations']));
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Error testing hours violation: ' . $e->getMessage());
        }
    }

    private function testSkillCoverage()
    {
        $weeklyScheduleData = [
            [
                'date_of_day' => Carbon::now()->startOfWeek()->format('Y-m-d'),
                'scheduled_start_time' => '09:00:00',
                'scheduled_end_time' => '17:00:00',
                'status_id' => 1,
                'emp_info_id' => 1,
                'employee' => [
                    'emp_info_id' => 1,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@test.com',
                    'skills' => [
                        ['skill_id' => 1, 'skill_name' => 'Customer Service', 'rating' => 4]
                        // Missing skill_id 2 (Cash Handling)
                    ],
                    'employment_info' => [
                        'position_id' => 1,
                        'store_id' => 1,
                        'max_weekly_hours' => 40,
                        'status' => 'active'
                    ]
                ],
                'required_skills' => [1, 2] // Skills that should be available
            ]
        ];

        try {
            $result = $this->weeklyScheduleService->validateAndProcessWeeklySchedule($weeklyScheduleData);
            if (!$result['validation_result']['valid'] && !empty($result['validation_result']['violations'])) {
                $this->info('   ✓ Skill coverage violation detected correctly');
                foreach ($result['validation_result']['violations'] as $violation) {
                    $this->info('   - ' . $violation);
                }
            } else {
                $this->error('   ✗ Skill coverage violation not detected');
                $this->info('   - Debug: Valid=' . ($result['validation_result']['valid'] ? 'true' : 'false'));
                $this->info('   - Debug: Violations count=' . count($result['validation_result']['violations']));
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Error testing skill coverage: ' . $e->getMessage());
        }
    }

    private function testEmployeeConflicts()
    {
        $weeklyScheduleData = [
            [
                'date_of_day' => Carbon::now()->startOfWeek()->format('Y-m-d'),
                'scheduled_start_time' => '09:00:00',
                'scheduled_end_time' => '17:00:00',
                'status_id' => 1,
                'emp_info_id' => 1,
                'employee' => [
                    'emp_info_id' => 1,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@test.com',
                    'skills' => [
                        ['skill_id' => 1, 'skill_name' => 'Customer Service', 'rating' => 4]
                    ],
                    'employment_info' => [
                        'position_id' => 1,
                        'store_id' => 1,
                        'max_weekly_hours' => 40,
                        'status' => 'active'
                    ]
                ],
                'required_skills' => [1]
            ],
            [
                'date_of_day' => Carbon::now()->startOfWeek()->format('Y-m-d'), // Same date
                'scheduled_start_time' => '14:00:00', // Overlapping time
                'scheduled_end_time' => '22:00:00',
                'status_id' => 1,
                'emp_info_id' => 1,
                'employee' => [
                    'emp_info_id' => 1, // Same employee
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@test.com',
                    'skills' => [
                        ['skill_id' => 1, 'skill_name' => 'Customer Service', 'rating' => 4]
                    ],
                    'employment_info' => [
                        'position_id' => 1,
                        'store_id' => 1,
                        'max_weekly_hours' => 40,
                        'status' => 'active'
                    ]
                ],
                'required_skills' => [1]
            ]
        ];

        try {
            $result = $this->weeklyScheduleService->validateAndProcessWeeklySchedule($weeklyScheduleData);
            if (!$result['validation_result']['valid'] && !empty($result['validation_result']['violations'])) {
                $this->info('   ✓ Employee scheduling conflict detected correctly');
                foreach ($result['validation_result']['violations'] as $violation) {
                    $this->info('   - ' . $violation);
                }
            } else {
                $this->error('   ✗ Employee scheduling conflict not detected');
                $this->info('   - Debug: Valid=' . ($result['validation_result']['valid'] ? 'true' : 'false'));
                $this->info('   - Debug: Violations count=' . count($result['validation_result']['violations']));
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Error testing employee conflicts: ' . $e->getMessage());
        }
    }

    private function testDataIntegrity()
    {
        // Test with invalid data structure
        $invalidData = [
            [
                'date_of_day' => 'invalid-date',
                'scheduled_start_time' => '25:00:00', // Invalid time
                'scheduled_end_time' => '08:00:00', // End before start
                'status_id' => 'invalid-status',
                'emp_info_id' => 'not-a-number',
                'employee' => [
                    'emp_info_id' => 'not-a-number',
                    // Missing required fields
                ],
                'required_skills' => 'not-an-array'
            ]
        ];

        $rules = (new \App\Http\Requests\WeeklyScheduleRequest())->rules();
        $validator = Validator::make(['weekly_schedule' => $invalidData], $rules);

        if ($validator->fails()) {
            $this->info('   ✓ Data integrity validation working correctly');
            $this->info('   - Validation errors detected: ' . count($validator->errors()->all()));
        } else {
            $this->error('   ✗ Data integrity validation failed to catch invalid data');
        }
    }
}
