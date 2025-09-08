<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StatusController extends Controller
{
    public function index()
    {
        return Status::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'slug' => 'nullable|string|max:255|unique:positions,slug'
        ]);

        if (!isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['description']);
        }

        return Status::create($validated);
    }

    public function show(Status $status)
    {
        return $status;
    }

    public function update(Request $request, Status $status)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('statuses', 'slug')->ignore($status->id)]
        ]);

        if (!isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['description']);
        }

        $status->update($validated);
        return $status;
    }

    public function destroy(Status $status)
    {
        $status->delete();
        return response()->noContent();
    }
    }
