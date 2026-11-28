<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected function formatUserPayload(User $user): array
    {
        $activeCompany = $user->isCompanyAdmin()
            ? $user->company
            : ($user->company ?? Company::first());

        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
            'company_id' => $activeCompany?->id ?? $user->company_id,
            'company' => $activeCompany ? [
                'id' => $activeCompany->id,
                'name' => $activeCompany->name,
                'slug' => $activeCompany->slug,
                'status' => $activeCompany->status,
                'whatsapp_number' => $activeCompany->whatsapp_number,
                'subscription_expires_at' => $activeCompany->subscription_expires_at,
                'is_active' => $activeCompany->isSubscriptionActive(),
            ] : null,
        ];

        if ($user->isSuperAdmin()) {
            $payload['available_companies'] = Company::orderBy('name')
                ->get(['id', 'name', 'status'])
                ->map(fn (Company $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'status' => $c->status,
                ])
                ->values();
        }

        return $payload;
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة، يرجى التأكد من البريد وكلمة المرور.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'هذا الحساب معطل حالياً أو بانتظار التفعيل من الإدارة.',
            ], 403);
        }

        if ($user->isCompanyAdmin() && (!$user->company || !$user->company->isSubscriptionActive())) {
            return response()->json([
                'message' => 'اشتراك منشأتك غير نشط أو بانتظار التفعيل من الإدارة.',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'token' => $token,
            'user' => $this->formatUserPayload($user->load('company')),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('company');

        return response()->json([
            'user' => $this->formatUserPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح',
        ]);
    }
}