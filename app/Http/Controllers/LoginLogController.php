<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use Illuminate\Http\Request;

class LoginLogController extends Controller
{
    public function index(Request $request)
    {
        $query = LoginLog::with('user:id,name')
            ->when($request->filled('phone'), fn($q) => $q->where('phone', 'ILIKE', "%{$request->phone}%"))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status === '1'))
            ->when($request->filled('login_from') && $request->filled('login_to'), function ($q) use ($request) {
                $q->whereBetween('login_at', [$request->login_from, $request->login_to]);
            })
            ->latest('login_at');

        $pageSize = $request->input('pageSize', 10);
        $currentPage = $request->input('currentPage', 1);

        $result = $query->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json([
            'code' => 200,
            'data' => [
                'list' => $result->items(),
                'total' => $result->total(),
                'pageSize' => $result->perPage(),
                'currentPage' => $result->currentPage()
            ]
        ]);
    }

    public function clearAll()
    {
        LoginLog::truncate();
        return response()->json(['code' => 200, 'message' => 'Đã xóa toàn bộ log']);
    }

    public function batchDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        LoginLog::whereIn('id', $request->ids)->delete();
        return response()->json(['code' => 200, 'message' => 'Xóa log thành công']);
    }
}
