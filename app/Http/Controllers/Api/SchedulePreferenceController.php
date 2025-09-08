<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchedulePreference;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchedulePreferenceController extends Controller
{
    public function index()
    {
        return SchedulePreference::with(['empInfo', 'preference'])->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'emp_info_id' => 'required|exists:emp_infos,id',
            'preference_id' => 'required|exists:preferences,id',
            'maximum_hours' => 'required|integer|min:1',
            'employment_type' => ['required', Rule::in(['FT', 'PT'])]
        ]);

        return SchedulePreference::create($validated);
    }

    public function show(SchedulePreference $schedulePreference)
    {
        return $schedulePreference->load(['empInfo', 'preference']);
    }

    public function update(Request $request, SchedulePreference $schedulePreference)
    {
        $validated = $request->validate([
            'emp_info_id' => 'required|exists:emp_infos,id',
            'preference_id' => 'required|exists:preferences,id',
            'maximum_hours' => 'required|integer|min:1',
            'employment_type' => ['required', Rule::in(['FT', 'PT'])]
        ]);

        $schedulePreference->update($validated);
        return $schedulePreference;
    }

    public function destroy(SchedulePreference $schedulePreference)
    {
        $schedulePreference->delete();
        return response()->noContent();
    }
    }
