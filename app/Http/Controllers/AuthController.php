<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('phone', 'password');

        if (auth()->attempt($credentials)) {
            $user = auth()->user();
            $token = $user->createToken('auth_token')->plainTextToken;

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
