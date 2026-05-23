<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkshopRequest;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Workshop::query();

        if ($request->filled('name')) {
            $query->where('name', 'ILIKE', '%' . $request->name . '%');
        }

        if (!is_null($request->is_active)) {
            $query->where('is_active', $request->is_active);
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(WorkshopRequest $request)
    {
        $workshop = Workshop::create($request->validated());
        return response()->json($workshop, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Workshop $workshop)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Workshop $workshop)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(WorkshopRequest $request, Workshop $workshop)
    {
        $validated = $request->validated();

        $workshop->update($validated);

        return response()->json([
            'message' => 'Câp nhật chi nhánh thành công',
            'data' => $workshop
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workshop $workshop)
    {
        if (!$workshop) {
            return response()->json([
                'message' => 'Chi nhánh không tồn tại'
            ], 404);
        }

        $workshop->delete();

        return response()->json([
            'message' => 'Chi nhánh đã được xóa thành công'
        ]);
    }
}
