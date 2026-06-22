<?php

namespace App\Http\Controllers;

use App\Models\JobPart;
use App\Models\JobOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobPartRequest;
use Illuminate\Http\Request;

class JobPartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(JobPartRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        return JobPart::create($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(JobPart $jobPart)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JobPart $jobPart)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, JobPart $jobPart)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JobPart $jobPart)
    {
        //
    }
    public function getPartsByOrderId($jobOrderId)
    {
        $jobOrder = JobOrder::findOrFail($jobOrderId);

        $parts = JobPart::with('part')
            ->where('job_order_id', $jobOrderId)
            ->get();

        return response()->json($parts);
    }
}
