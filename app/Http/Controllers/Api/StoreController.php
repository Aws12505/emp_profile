<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index()
    {
        return Store::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string|unique:stores,id',
            'name' => 'required|string|max:255',
            'number' => 'required|string|max:255',
        ]);

        return Store::create($validated);
    }

    public function show(Store $store)
    {
        return $store;
    }

    public function update(Request $request, Store $store)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'number' => 'required|string|max:255',
        ]);

        $store->update($validated);
        return $store;
    }

    public function destroy(Store $store)
    {
        $store->delete();
        return response()->noContent();
    }
}
