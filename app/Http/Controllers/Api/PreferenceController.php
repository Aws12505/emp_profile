<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Preference;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public function index()
    {
        return Preference::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:preferences,slug'
        ]);

        if (!isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        return Preference::create($validated);
    }

    public function show(Preference $preference)
    {
        return $preference->load('schedulePreferences');
    }

    public function update(Request $request, Preference $preference)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('preferences', 'slug')->ignore($preference->id)]
        ]);

        if (!isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $preference->update($validated);
        return $preference;
    }

    public function destroy(Preference $preference)
    {
        $preference->delete();
        return response()->noContent();
    }
    }
