<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    public function index(Request $request)
    {
        // Không truyền pageSize → trả toàn bộ lỗi đang dùng (cho form tiếp nhận)
        if (!$request->has('pageSize')) {
            return response()->json(
                Issue::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
            );
        }

        $query = Issue::query()
            ->when($request->filled('name'), fn($q) =>
                $q->where('name', 'ILIKE', "%{$request->name}%")
            )
            ->orderBy('sort_order')
            ->orderBy('name');

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
            'name'       => 'required|string|unique:issues,name',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $issue = Issue::create($request->only('name', 'sort_order', 'is_active'));

        return response()->json([
            'code'    => 200,
            'message' => 'Thêm lỗi thành công',
            'data'    => $issue,
        ], 201);
    }

    public function update(Request $request, Issue $issue)
    {
        $request->validate([
            'name'       => "required|string|unique:issues,name,{$issue->id}",
            'sort_order' => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $issue->update($request->only('name', 'sort_order', 'is_active'));

        return response()->json([
            'code'    => 200,
            'message' => 'Cập nhật lỗi thành công',
        ]);
    }

    public function destroy(Issue $issue)
    {
        $issue->delete();

        return response()->json([
            'code'    => 200,
            'message' => 'Xóa lỗi thành công',
        ]);
    }
}