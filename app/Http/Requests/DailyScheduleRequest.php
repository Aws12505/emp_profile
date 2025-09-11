<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyScheduleRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Check if this is a day-level array request or individual schedule
        if ($this->has('date_of_day') && $this->has('schedules')) {
            // Day-level array validation
            return [
                'date_of_day' => 'required|date',
                'schedules' => 'required|array|min:1',
                'schedules.*.emp_info_id' => 'required|exists:emp_infos,id',
                'schedules.*.scheduled_start_time' => 'required|date_format:H:i:s',
                'schedules.*.scheduled_end_time' => 'required|date_format:H:i:s|after:schedules.*.scheduled_start_time',
                'schedules.*.actual_start_time' => 'nullable|date_format:H:i:s',
                'schedules.*.actual_end_time' => 'nullable|date_format:H:i:s|after:schedules.*.actual_start_time',
                'schedules.*.vci' => 'nullable|boolean',
                'schedules.*.status_id' => 'required|exists:statuses,id',
                'schedules.*.agree_on_exception' => 'boolean',
                'schedules.*.exception_notes' => 'nullable|string|max:1000',
                'schedules.*.required_skills' => 'nullable|array',
                'schedules.*.required_skills.*' => 'exists:skills,id',
                // Remove embedded employee data validation - backend will retrieve employee details
            ];
        }
        
        // Individual schedule validation (backward compatibility)
        $rules = [
            'date_of_day' => 'required|date',
            'emp_info_id' => 'required|exists:emp_infos,id',
            'scheduled_start_time' => 'required|date_format:H:i:s',
            'scheduled_end_time' => 'required|date_format:H:i:s|after:scheduled_start_time',
            'actual_start_time' => 'nullable|date_format:H:i:s',
            'actual_end_time' => 'nullable|date_format:H:i:s|after:actual_start_time',
            'vci' => 'nullable|boolean',
            'status_id' => 'required|exists:statuses,id',
            'agree_on_exception' => 'boolean',
            'exception_notes' => 'nullable|string|max:1000',
            'required_skills' => 'nullable|array',
            'required_skills.*' => 'exists:skills,id'
        ];

        // For updates, make emp_info_id and date_of_day sometimes required
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['emp_info_id'] = 'sometimes|required|exists:emp_infos,id';
            $rules['date_of_day'] = 'sometimes|required|date';
        }

        return $rules;
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'date_of_day.required' => 'The schedule date is required.',
            'schedules.required' => 'At least one schedule is required.',
            'schedules.min' => 'At least one schedule is required.',
            'schedules.*.emp_info_id.required' => 'The employee is required for each schedule.',
            'schedules.*.emp_info_id.exists' => 'The selected employee does not exist.',
            'schedules.*.scheduled_start_time.required' => 'The scheduled start time is required for each schedule.',
            'schedules.*.scheduled_end_time.required' => 'The scheduled end time is required for each schedule.',
            'schedules.*.scheduled_end_time.after' => 'The scheduled end time must be after the start time.',
            'schedules.*.actual_end_time.after' => 'The actual end time must be after the actual start time.',
            'schedules.*.status_id.required' => 'The status is required for each schedule.',
            'schedules.*.status_id.exists' => 'The selected status does not exist.',
            'schedules.*.required_skills.*.exists' => 'One or more selected skills do not exist.',
            'schedules.*.exception_notes.max' => 'Exception notes cannot exceed 1000 characters.',
            // Individual schedule messages (backward compatibility)
            'emp_info_id.required' => 'The employee is required.',
            'emp_info_id.exists' => 'The selected employee does not exist.',
            'scheduled_start_time.required' => 'The scheduled start time is required.',
            'scheduled_end_time.required' => 'The scheduled end time is required.',
            'scheduled_end_time.after' => 'The scheduled end time must be after the start time.',
            'actual_end_time.after' => 'The actual end time must be after the actual start time.',
            'status_id.required' => 'The status is required.',
            'status_id.exists' => 'The selected status does not exist.',
            'required_skills.*.exists' => 'One or more selected skills do not exist.',
            'exception_notes.max' => 'Exception notes cannot exceed 1000 characters.'
        ];
    }

    /**
     * Get custom attribute names for validation errors
     */
    public function attributes(): array
    {
        return [
            'date_of_day' => 'schedule date',
            'emp_info_id' => 'employee',
            'scheduled_start_time' => 'scheduled start time',
            'scheduled_end_time' => 'scheduled end time',
            'actual_start_time' => 'actual start time',
            'actual_end_time' => 'actual end time',
            'status_id' => 'status',
            'required_skills' => 'required skills',
            'exception_notes' => 'exception notes'
        ];
    }
}
