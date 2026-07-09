<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use App\Models\LoginLog;

class AuthController extends Controller
{


    public function login(LoginRequest $request)
    {
        $credentials = $request->only('phone', 'password');

        $agent = $request->userAgent() ?? '';

        $browser = 'Unknown';
        if (str_contains($agent, 'Edg')) $browser = 'Edge';
        elseif (str_contains($agent, 'Chrome')) $browser = 'Chrome';
        elseif (str_contains($agent, 'Firefox')) $browser = 'Firefox';
        elseif (str_contains($agent, 'Safari')) $browser = 'Safari';

        $system = 'Unknown';
        if (str_contains($agent, 'Windows')) $system = 'Windows';
        elseif (str_contains($agent, 'Android')) $system = 'Android';
        elseif (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad')) $system = 'iOS';
        elseif (str_contains($agent, 'Mac')) $system = 'macOS';
        elseif (str_contains($agent, 'Linux')) $system = 'Linux';

        $logData = [
            'phone'      => $request->phone,
            'ip'         => $request->ip(),
            'user_agent' => $agent,
            'browser'    => $browser,
            'system'     => $system,
            'login_at'   => now(),
        ];

        if (auth()->attempt($credentials)) {
            $user = auth()->user();
            $token = $user->createToken('auth_token')->plainTextToken;

            // Ghi log thành công
            LoginLog::create([
                ...$logData,
                'user_id' => $user->id,
                'status'  => true,
            ]);

            return response()->json([
                'code' => 200,
                'message' => 'Đăng nhập thành công',
                'data' => [
                    'phone' => $user->phone,
                    'name' => $user->name,
                    'roles' => $user->getRoleNames()->toArray(),
                    'permissions' => $user->hasRole('admin')
                        ? ["*:*:*"]
                        : $user->getAllPermissions()->pluck('name')->toArray(),
                    'accessToken' => $token
                ]
            ]);
        }

        // Ghi log thất bại
        LoginLog::create([
            ...$logData,
            'status' => false,
        ]);

        return response()->json([
            'code' => 401,
            'message' => 'Invalid credentials'
        ], 401);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'code' => 200,
            'message' => 'Đăng xuất thành công'
        ]);
    }
}
