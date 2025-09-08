<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmpEmploymentInfo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmpEmploymentInfoController extends Controller
{
    public function index()
    {
        return EmpEmploymentInfo::with(['empInfo', 'position'])->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'emp_info_id' => 'required|exists:emp_infos,id',
            'position_id' => 'nullable|exists:positions,id',
            'paychex_ids' => 'required|array',
            'employment_type' => ['required', Rule::in(['1099', 'W2'])],
            'hired_date' => 'required|date',
            'base_pay' => 'required|numeric|min:0',
            'performance_pay' => 'required|numeric|min:0',
            'has_uniform' => 'required|boolean'
        ]);

        return EmpEmploymentInfo::create($validated);
    }

    public function show(EmpEmploymentInfo $employmentInfo)
    {
        return $employmentInfo->load(['empInfo', 'position']);
    }

    public function update(Request $request, EmpEmploymentInfo $employmentInfo)
    {
        $validated = $request->validate([
            'emp_info_id' => 'required|exists:emp_infos,id',
            'position_id' => 'nullable|exists:positions,id',
            'paychex_ids' => 'required|array',
            'employment_type' => ['required', Rule::in(['1099', 'W2'])],
            'hired_date' => 'required|date',
            'base_pay' => 'required|numeric|min:0',
            'performance_pay' => 'required|numeric|min:0',
            'has_uniform' => 'required|boolean'
        ]);

        $employmentInfo->update($validated);
        return $employmentInfo;
    }

    public function destroy(EmpEmploymentInfo $employmentInfo)
    {
        $employmentInfo->delete();
        return response()->noContent();
    }
    }
