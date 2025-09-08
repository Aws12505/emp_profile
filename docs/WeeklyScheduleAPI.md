# Weekly Schedule API Documentation

## Overview

The Weekly Schedule API provides endpoints for processing and validating complete weekly schedules. The system processes weekly schedule arrays as single entities, performing comprehensive validation across the entire week's data.

## Endpoints

### 1. Process Weekly Schedule

**POST** `/api/weekly-schedules/process`

Processes and validates a complete weekly schedule array with embedded employee data.

#### Request Structure

**New Day-Level Format (Recommended - Supports Split Shifts)**:

```json
{
  "weekly_schedule": [
    {
      "date_of_day": "2024-01-15",
      "schedules": [
        {
          "emp_info_id": 1,
          "scheduled_start_time": "09:00:00",
          "scheduled_end_time": "13:00:00",
          "actual_start_time": null,
          "actual_end_time": null,
          "vci": false,
          "status_id": 1,
          "agree_on_exception": false,
          "exception_notes": null,
          "employee": {
            "emp_info_id": 1,
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@company.com",
            "skills": [
              {
                "skill_id": 1,
                "skill_name": "Customer Service",
                "rating": 4
              },
              {
                "skill_id": 2,
                "skill_name": "Cash Handling",
                "rating": 5
              }
            ],
            "employment_info": {
              "position_id": 1,
              "store_id": 1,
              "max_weekly_hours": 40,
              "status": "active"
            }
          },
          "required_skills": [1, 2]
        },
        {
          "emp_info_id": 1,
          "scheduled_start_time": "14:00:00",
          "scheduled_end_time": "18:00:00",
          "actual_start_time": null,
          "actual_end_time": null,
          "vci": false,
          "status_id": 1,
          "agree_on_exception": false,
          "exception_notes": null,
          "employee": {
            "emp_info_id": 1,
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@company.com",
            "skills": [
              {
                "skill_id": 1,
                "skill_name": "Customer Service",
                "rating": 4
              },
              {
                "skill_id": 2,
                "skill_name": "Cash Handling",
                "rating": 5
              }
            ],
            "employment_info": {
              "position_id": 1,
              "store_id": 1,
              "max_weekly_hours": 40,
              "status": "active"
            }
          },
          "required_skills": [1]
        }
      ]
    },
    {
      "date_of_day": "2024-01-16",
      "schedules": [
        {
          "emp_info_id": 2,
          "scheduled_start_time": "10:00:00",
          "scheduled_end_time": "18:00:00",
          "actual_start_time": null,
          "actual_end_time": null,
          "vci": false,
          "status_id": 1,
          "agree_on_exception": false,
          "exception_notes": null,
          "employee": {
            "emp_info_id": 2,
            "first_name": "Jane",
            "last_name": "Smith",
            "email": "jane.smith@company.com",
            "skills": [
              {
                "skill_id": 1,
                "skill_name": "Customer Service",
                "rating": 5
              },
              {
                "skill_id": 3,
                "skill_name": "Inventory Management",
                "rating": 4
              }
            ],
            "employment_info": {
              "position_id": 2,
              "store_id": 1,
              "max_weekly_hours": 35,
              "status": "active"
            }
          },
          "required_skills": [1, 3]
        }
      ]
    }
  ]
}
```

**Legacy Format (Backward Compatible)**:

```json
{
  "weekly_schedule": [
    {
      "date_of_day": "2024-01-15",
      "emp_info_id": 1,
      "scheduled_start_time": "09:00:00",
      "scheduled_end_time": "17:00:00",
      "actual_start_time": null,
      "actual_end_time": null,
      "vci": false,
      "status_id": 1,
      "agree_on_exception": false,
      "exception_notes": null,
      "employee": {
        "emp_info_id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john.doe@company.com",
        "skills": [
          {
            "skill_id": 1,
            "skill_name": "Customer Service",
            "rating": 4
          }
        ],
        "employment_info": {
          "position_id": 1,
          "store_id": 1,
          "max_weekly_hours": 40,
          "status": "active"
        }
      },
      "required_skills": [1]
    }
  ]
}
```

#### Validation Rules

**New Day-Level Format**:

1. **Weekly Schedule Array**:
   - Must be an array with at least 1 day entry
   - Maximum 7 entries (one per day of the week)
   - Each day must have a unique `date_of_day` within the same week

2. **Day-Level Entry**:
   - `date_of_day`: Required, valid date format (Y-m-d)
   - `schedules`: Required array with at least 1 schedule entry

3. **Individual Schedule Entry** (within `schedules` array):
   - `emp_info_id`: Required, integer, must exist in emp_infos table
   - `scheduled_start_time`: Required, valid time format (H:i:s)
   - `scheduled_end_time`: Required, valid time format (H:i:s), must be after start_time
   - `actual_start_time`: Optional, valid time format (H:i:s)
   - `actual_end_time`: Optional, valid time format (H:i:s)
   - `vci`: Optional, boolean
   - `status_id`: Required, integer, must exist in statuses table
   - `agree_on_exception`: Optional, boolean, defaults to false
   - `exception_notes`: Optional, text
   - `employee`: Required object with complete employee data
   - `required_skills`: Optional array of skill IDs

4. **Split Shift Validation**:
   - Multiple schedules for the same employee on the same day are allowed
   - No time overlaps allowed for the same employee on the same day
   - Minimum 30-minute break required between shifts for the same employee

**Legacy Format** (Backward Compatible):

1. **Weekly Schedule Array**:
   - Must be an array with at least 1 schedule entry
   - Maximum entries limited by business rules
   - Each entry represents a single schedule

2. **Individual Schedule Entry**:
   - `date_of_day`: Required, valid date format (Y-m-d)
   - `emp_info_id`: Required, integer, must exist in emp_infos table
   - `scheduled_start_time`: Required, valid time format (H:i:s)
   - `scheduled_end_time`: Required, valid time format (H:i:s), must be after start_time
   - `actual_start_time`: Optional, valid time format (H:i:s)
   - `actual_end_time`: Optional, valid time format (H:i:s)
   - `vci`: Optional, boolean
   - `status_id`: Required, integer, must exist in statuses table
   - `agree_on_exception`: Optional, boolean, defaults to false
   - `exception_notes`: Optional, text
   - `employee`: Required object with complete employee data
   - `required_skills`: Optional array of skill IDs

3. **Employee Data**:
   - `emp_info_id`: Required, must be consistent across all entries for the same employee
   - `first_name`, `last_name`, `email`: Required strings
   - `skills`: Required array with skill objects containing skill_id, skill_name, and rating
   - `employment_info`: Required object with position_id, store_id, max_weekly_hours, and status

4. **Business Rules**:
   - Employee cannot exceed their maximum weekly hours
   - Employee cannot be scheduled for overlapping time slots
   - All required skills for each day must be covered by scheduled employees
   - Employees must possess the required skills for their assigned shifts
   - Split shifts are supported with minimum 30-minute breaks between shifts
   - Maximum daily hours per employee: 12 hours (across all shifts)
   - Day-level validation ensures comprehensive skill coverage and conflict detection

#### Response Structure

**Success Response (200)**:
```json
{
  "success": true,
  "message": "Weekly schedule processed successfully",
  "data": {
    "processed_schedules": 2,
    "total_hours": 16,
    "employees_scheduled": 2,
    "skill_coverage": {
      "covered_skills": [1, 2, 3],
      "missing_skills": []
    },
    "validation_summary": {
      "hours_violations": [],
      "skill_violations": [],
      "conflict_violations": []
    }
  }
}
```

**Validation Error Response (422)**:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "weekly_schedule.0.end_time": ["End time must be after start time"],
    "weekly_schedule.1.employee.skills": ["Employee must have required skills"]
  },
  "violations": {
    "hours_violations": [
      {
        "employee_id": 1,
        "scheduled_hours": 45,
        "max_hours": 40,
        "violation": "Exceeds maximum weekly hours by 5 hours"
      }
    ],
    "skill_violations": [
      {
        "date": "2024-01-15",
        "required_skills": [1, 2],
        "missing_skills": [2],
        "violation": "Required skill 'Cash Handling' not covered"
      }
    ]
  }
}
```

### 2. Get Weekly Schedule Analysis

**GET** `/api/weekly-schedules/analysis`

Provides comprehensive analysis of weekly schedule patterns and constraints.

#### Query Parameters

- `start_date` (optional): Start date for analysis (Y-m-d format)
- `end_date` (optional): End date for analysis (Y-m-d format)
- `store_id` (optional): Filter by specific store
- `employee_id` (optional): Filter by specific employee

#### Response Structure

```json
{
  "success": true,
  "data": {
    "analysis_period": {
      "start_date": "2024-01-15",
      "end_date": "2024-01-21"
    },
    "summary": {
      "total_schedules": 14,
      "total_employees": 5,
      "total_hours": 280,
      "average_hours_per_employee": 56
    },
    "skill_coverage": {
      "required_skills": [1, 2, 3, 4],
      "coverage_percentage": 95,
      "gaps": [
        {
          "date": "2024-01-20",
          "missing_skills": [4],
          "impact": "Low priority skill gap"
        }
      ]
    },
    "hours_analysis": {
      "employees_at_max_hours": 2,
      "employees_under_utilized": 1,
      "potential_overtime": 0
    },
    "conflicts": []
  }
}
```

## Implementation Notes for Frontend Developers

### 1. Data Preparation

- Ensure all employee data is complete before sending the request
- Validate time formats on the frontend to prevent server-side errors
- Include all required employee skills and employment information

### 2. Error Handling

- Handle both validation errors (422) and business rule violations
- Display specific error messages for each field
- Show violation summaries to help users understand scheduling conflicts

### 3. User Experience

- Provide real-time validation feedback as users build schedules
- Show skill coverage indicators for each day
- Display running totals of hours per employee
- Highlight potential conflicts before submission

### 4. Performance Considerations

- Batch weekly schedules rather than sending individual day requests
- Cache employee data to avoid redundant API calls
- Use the analysis endpoint to pre-validate scheduling constraints

## Example Usage

### JavaScript/Fetch Example

```javascript
const weeklyScheduleData = {
  weekly_schedule: [
    {
      date: '2024-01-15',
      start_time: '09:00:00',
      end_time: '17:00:00',
      status: 'scheduled',
      employee: {
        emp_info_id: 1,
        first_name: 'John',
        last_name: 'Doe',
        email: 'john.doe@company.com',
        skills: [
          { skill_id: 1, skill_name: 'Customer Service', rating: 4 },
          { skill_id: 2, skill_name: 'Cash Handling', rating: 5 }
        ],
        employment_info: {
          position_id: 1,
          store_id: 1,
          max_weekly_hours: 40,
          status: 'active'
        }
      },
      required_skills: [1, 2]
    }
  ]
};

fetch('/api/weekly-schedules/process', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify(weeklyScheduleData)
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    console.log('Schedule processed successfully:', data.data);
  } else {
    console.error('Validation errors:', data.errors);
    console.error('Business rule violations:', data.violations);
  }
})
.catch(error => {
  console.error('Network error:', error);
});
```

## Validation Flow

1. **Input Validation**: Basic field validation (required fields, formats, data types)
   - Validates request structure (day-level vs legacy format)
   - Checks required fields and data types
   - Validates time formats and date ranges

2. **Day-Level Structure Validation**: 
   - Ensures each day has valid schedules array
   - Validates individual schedule entries within each day
   - Checks for duplicate dates within the week

3. **Split Shift Validation**:
   - Validates time overlaps within the same day for each employee
   - Ensures minimum break time between shifts
   - Checks daily hour limits across all shifts

4. **Business Rule Validation**: Weekly hours, skill requirements, scheduling conflicts
5. **Data Integrity Checks**: Employee consistency, date ranges, time logic
6. **Skill Coverage Analysis**: Ensure all required skills are covered across the week
7. **Cross-Day Conflict Detection**: Identify overlapping schedules and resource conflicts

## Error Codes

- **400 Bad Request**: Invalid request format or missing required parameters
  - Invalid JSON structure
  - Missing required fields
  - Invalid data types
  - Malformed date/time formats

- **422 Unprocessable Entity**: Validation errors (business rules, conflicts, skill coverage)
  - `SCHEDULE_OVERLAP`: Time overlaps detected for the same employee within a day
  - `INSUFFICIENT_BREAK_TIME`: Less than 30-minute break between shifts for the same employee
  - `DAILY_HOUR_LIMIT_EXCEEDED`: Employee scheduled for more than 12 hours in a single day
  - `SKILL_COVERAGE_INSUFFICIENT`: Required skills not adequately covered for a day
  - `EMPLOYEE_UNAVAILABLE`: Employee not available during scheduled time
  - `CROSS_DAY_CONFLICT`: Employee has conflicting schedules across different days
  - `INVALID_EMPLOYEE_SKILLS`: Employee lacks required skills for assigned shift
  - `DUPLICATE_DATE`: Multiple entries for the same date within the week
  - `INVALID_SCHEDULE_STRUCTURE`: Malformed day-level or legacy schedule structure

- **500 Internal Server Error**: Server-side processing errors
  - Database connection issues
  - Unexpected validation failures
  - System configuration errors

This API is designed to handle complex scheduling scenarios including split shifts while maintaining data integrity and providing clear feedback for validation errors. The new day-level structure enables more sophisticated scheduling patterns while preserving backward compatibility with existing implementations.