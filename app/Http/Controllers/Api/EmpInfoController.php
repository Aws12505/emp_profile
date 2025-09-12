<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmpInfo;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class EmpInfoController extends Controller
{
    public function index()
    {
        return EmpInfo::with(['store', 'skills', 'schedulePreferences', 'employmentInfo'])->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'has_family' => 'required|boolean',
            'has_car' => 'required|boolean',
            'is_arabic_team' => 'required|boolean',
            'notes' => 'nullable|string',
            'status' => ['required', Rule::in(['Active', 'Suspension', 'Terminated', 'On Leave'])]
        ]);

        return EmpInfo::create($validated);
    }

    public function show(EmpInfo $employee)
    {
        return $employee->load(['store', 'skills', 'schedulePreferences', 'employmentInfo']);
    }

    public function update(Request $request, EmpInfo $employee)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'has_family' => 'required|boolean',
            'has_car' => 'required|boolean',
            'is_arabic_team' => 'required|boolean',
            'notes' => 'nullable|string',
            'status' => ['required', Rule::in(['Active', 'Suspension', 'Terminated', 'On Leave'])]
        ]);

        $employee->update($validated);
        return $employee;
    }

    public function destroy(EmpInfo $employee)
    {
        $employee->delete();
        return response()->noContent();
    }

    public function attachSkill(Request $request, EmpInfo $employee, Skill $skill)
    {
        $validated = $request->validate([
            'rating' => ['required', Rule::in(['A+', 'A', 'B', 'C', 'D'])]
        ]);

        $employee->skills()->attach($skill->id, ['rating' => $validated['rating']]);
        return response()->noContent();
    }

    public function detachSkill(EmpInfo $employee, Skill $skill)
    {
        $employee->skills()->detach($skill->id);
        return response()->noContent();
    }

    public function updateSkillRating(Request $request, EmpInfo $employee, Skill $skill)
    {
        $validated = $request->validate([
            'rating' => ['required', Rule::in(['A+', 'A', 'B', 'C', 'D'])]
        ]);

        $employee->skills()->updateExistingPivot($skill->id, ['rating' => $validated['rating']]);
        return response()->noContent();
    }

   public function getUsersByStoreId(Request $request, string $storeId)
{
    // Validate path + optional query params
    $validated = Validator::make(
        array_merge(['store_id' => $storeId], $request->only(['status', 'per_page'])),
        [
            'store_id' => 'required|exists:stores,id', // id column is string-based
            'status'   => ['nullable', Rule::in(['Active', 'Suspension', 'Terminated', 'On Leave'])],
            'per_page' => 'nullable|integer|min:1|max:100'
        ]
    )->validate();

    $query = EmpInfo::with(['store', 'skills', 'schedulePreferences', 'employmentInfo'])
        ->where('store_id', $validated['store_id']);

    if (!empty($validated['status'])) {
        $query->where('status', $validated['status']);
    }

    $perPage = $validated['per_page'] ?? 15;

    return $query->paginate($perPage);
}
}
