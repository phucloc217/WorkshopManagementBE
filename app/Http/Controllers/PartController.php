<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\WarehouseParts;
use App\Models\JobPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartController extends Controller
{
    public function index(Request $request)
    {
        // Không truyền pageSize → trả toàn bộ (dùng cho dropdown chọn linh kiện)
        if (!$request->has('pageSize')) {
            return response()->json(
                Part::orderBy('part_code')->get()
            );
        }

        $query = Part::query()
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $keyword = $request->keyword;
                $q->where(function ($q) use ($keyword) {
                    $q->where('part_code', 'ILIKE', "%{$keyword}%")
                        ->orWhere('description', 'ILIKE', "%{$keyword}%");
                });
            })
            ->orderBy('part_code');

        $result = $query->paginate(
            $request->input('pageSize', 10),
            ['*'],
            'page',
            $request->input('currentPage', 1)
        );

        return response()->json([
            'code' => 200,
            'data' => [
                'list'        => $result->items(),
                'total'       => $result->total(),
                'pageSize'    => $result->perPage(),
                'currentPage' => $result->currentPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'part_code'   => 'required|string|unique:parts,part_code',
            'description' => 'required|string',
            'min_qty'     => 'nullable|integer|min:0',
        ]);

        $part = Part::create([
            'part_code'   => strtoupper(trim($request->part_code)),
            'description' => trim($request->description),
            'min_qty'     => $request->min_qty ?? 0,
        ]);

        return response()->json([
            'code'    => 200,
            'message' => 'Thêm hàng hóa thành công',
            'data'    => $part,
        ], 201);
    }

    public function update(Request $request, Part $part)
    {
        $request->validate([
            'part_code'   => "required|string|unique:parts,part_code,{$part->id}",
            'description' => 'required|string',
            'min_qty'     => 'nullable|integer|min:0',
        ]);

        $part->update([
            'part_code'   => strtoupper(trim($request->part_code)),
            'description' => trim($request->description),
            'min_qty'     => $request->min_qty ?? 0,
        ]);

        return response()->json([
            'code'    => 200,
            'message' => 'Cập nhật hàng hóa thành công',
        ]);
    }

    public function destroy(Part $part)
    {
        if (WarehouseParts::where('part_id', $part->id)->where('qty', '>', 0)->exists()) {
            return response()->json([
                'message' => 'Hàng hóa còn tồn trong kho, không thể xóa'
            ], 422);
        }

        if (JobPart::where('part_id', $part->id)->exists()) {
            return response()->json([
                'message' => 'Hàng hóa đã được dùng trong phiếu sửa chữa, không thể xóa'
            ], 422);
        }

        DB::transaction(function () use ($part) {
            // Dọn bản ghi tồn kho rỗng còn sót
            WarehouseParts::where('part_id', $part->id)->delete();
            $part->delete();
        });

        return response()->json([
            'code'    => 200,
            'message' => 'Xóa hàng hóa thành công',
        ]);
    }

    /**
     * Import danh sách hàng hóa từ Excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'rows'               => 'required|array|min:1',
            'rows.*.part_code'   => 'required|string',
            'rows.*.description' => 'required|string',
            'rows.*.min_qty'     => 'nullable|integer|min:0',
        ]);

        $created = 0;
        $updated = 0;
        $errors  = [];

        DB::transaction(function () use ($request, &$created, &$updated, &$errors) {
            foreach ($request->rows as $index => $row) {
                $code = strtoupper(trim($row['part_code']));

                if ($code === '') {
                    $errors[] = [
                        'row'     => $index + 2,
                        'message' => 'Thiếu mã hàng hóa',
                    ];
                    continue;
                }

                $part = Part::where('part_code', $code)->first();

                if ($part) {
                    $part->update([
                        'description' => trim($row['description']),
                        'min_qty'     => $row['min_qty'] ?? $part->min_qty ?? 0,
                    ]);
                    $updated++;
                } else {
                    Part::create([
                        'part_code'   => $code,
                        'description' => trim($row['description']),
                        'min_qty'     => $row['min_qty'] ?? 0,
                    ]);
                    $created++;
                }
            }
        });

        return response()->json([
            'message' => "Thêm mới {$created}, cập nhật {$updated} hàng hóa",
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        ]);
    }
}