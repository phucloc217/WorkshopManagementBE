<?php

namespace App\Http\Controllers;

use App\Models\JobPart;
use App\Models\JobOrder;
use App\Models\Part;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobPartRequest;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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
        if ($jobPart->qty_issued !== 0) {
            return response()->json([
                'message' => 'Chỉ được các linh kiện chưa nhận từ kho'
            ], 422);
        }


        $jobPart->delete();


        return response()->json([
            'message' => 'Xóa linh kiện thành công.'
        ]);
    }
    public function getPartsByOrderId($jobOrderId)
    {
        $jobOrder = JobOrder::findOrFail($jobOrderId);

        $parts = JobPart::with('part')
            ->where('job_order_id', $jobOrderId)
            ->get();

        return response()->json($parts);
    }
    public function import(Request $request, string $id)
    {
        $rows = $request->all();

        $errors = [];
        $inserted = 0;

        foreach ($rows as $index => $row) {

            $part = Part::where('part_code', trim($row['part_code']))->first();

            if (!$part) {
                $errors[] = [
                    'row' => $index + 1,
                    'part_code' => $row['part_code'],
                    'message' => 'Part code không tồn tại'
                ];
                continue;
            }

            JobPart::create([
                'job_order_id' => $id,
                'part_id' => $part->id,
                'qty' => (int) $row['qty'],
                'is_warranty' => (bool) $row['is_warranty'],
            ]);

            $inserted++;
        }

        return response()->json([
            'message' => 'Import hoàn tất',
            'inserted' => $inserted,
            'errors' => $errors
        ]);
    }
}
