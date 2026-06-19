<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Models\Vehicle;
use App\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class JobOrdercontroller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = JobOrder::with([
            'customer:id,name',
            'vehicle:id,motor_number,vin,model',

        ])->get();
        return response()->json($query);
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
    public function store(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'name' => 'required',
            'motor_number' => 'required',
            'description' => 'nullable|string'
        ]);
        $customer = Customer::where('phone', $request->phone)->first();
        if (!$customer) {
            $customer = Customer::create([
                '$id' => uniqid(),
                'name' => $request->name,
                'phone' => $request->phone,
            ]);
        }
        $vehicle = Vehicle::where('motor_number', $request->motor_number)->first();
        if (!$vehicle) {
            $vehicle =  Vehicle::create([
                '$id' => uniqid(),
                'motor_number' => $request->motor_number,
                'vin' => $request->vin,
            ]);
        }
        $request->merge(['vehicle_id' => $vehicle->id]);
        $request->merge(['customer_id' => $customer->id]);
        $request['issue_description'] = $request->type === 'GSM'
            ? implode(PHP_EOL, $request->issue_description ?? [])
            : implode('; ', $request->issue_description ?? []);
        $request['order_no'] = $this->generateOrderNo();
        $jobOrder = JobOrder::create($request->all());

        return response()->json($jobOrder, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(JobOrder $jobOrder)
    {
        return response()->json($jobOrder->load([
            'customer:id,name,phone',
            'vehicle:id,motor_number,vin,model',
            'workshop:id,name',
        ]));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JobOrder $jobOrder)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, JobOrder $jobOrder)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JobOrder $jobOrder)
    {
        //
    }
    private function generateOrderNo(): string
    {
        $today = Carbon::now()->format('dmy'); // 090626

        $prefix = "SC/{$today}/";

        $lastOrder = JobOrder::where('order_no', 'like', "{$prefix}%")
            ->orderByDesc('order_no')
            ->first();

        if (!$lastOrder) {
            $sequence = 1;
        } else {
            $sequence = (int) substr($lastOrder->order_no, -4) + 1;
        }

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
