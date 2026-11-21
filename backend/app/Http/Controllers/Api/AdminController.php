<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\SystemNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * Dashboard statistics for Super Admin.
     */
    public function dashboard(): JsonResponse
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('subscription_expires_at')
                  ->orWhere('subscription_expires_at', '>', Carbon::now());
            })->count();

        $suspendedCompanies = Company::where('status', '!=', 'active')
            ->orWhere('subscription_expires_at', '<=', Carbon::now())
            ->count();

        $totalConversations = Conversation::count();
        $recentNotifications = SystemNotification::latest()->take(10)->get();
        $unreadNotificationsCount = SystemNotification::where('is_read', false)->count();

        $recentCompanies = Company::withCount(['documents', 'conversations'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_companies' => $totalCompanies,
                'active_companies' => $activeCompanies,
                'suspended_companies' => $suspendedCompanies,
                'total_conversations' => $totalConversations,
                'unread_notifications' => $unreadNotificationsCount,
            ],
            'recent_companies' => $recentCompanies,
            'recent_notifications' => $recentNotifications,
        ]);
    }

    /**
     * List all companies.
     */
    public function companies(Request $request): JsonResponse
    {
        $query = Company::withCount(['documents', 'conversations'])->with('users');

        if ($request->has('search') && $request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%$s%")
                  ->orWhere('whatsapp_number', 'like', "%$s%")
                  ->orWhere('phone', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%");
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $companies = $query->latest()->get()->map(function ($comp) {
            $isExpired = $comp->subscription_expires_at && $comp->subscription_expires_at->isPast();
            return [
                'id' => $comp->id,
                'name' => $comp->name,
                'slug' => $comp->slug,
                'phone' => $comp->phone,
                'whatsapp_number' => $comp->whatsapp_number,
                'email' => $comp->email,
                'address' => $comp->address,
                'status' => $comp->status,
                'is_active' => $comp->isSubscriptionActive(),
                'is_expired' => $isExpired,
                'subscription_plan' => $comp->subscription_plan,
                'subscription_starts_at' => $comp->subscription_starts_at?->format('Y-m-d'),
                'subscription_expires_at' => $comp->subscription_expires_at?->format('Y-m-d'),
                'days_remaining' => $comp->subscription_expires_at ? max(0, (int) Carbon::now()->diffInDays($comp->subscription_expires_at, false)) : null,
                'documents_count' => $comp->documents_count,
                'conversations_count' => $comp->conversations_count,
                'admin_user' => $comp->users->first() ? [
                    'id' => $comp->users->first()->id,
                    'name' => $comp->users->first()->name,
                    'email' => $comp->users->first()->email,
                ] : null,
            ];
        });

        return response()->json([
            'companies' => $companies,
        ]);
    }

    /**
     * Create a new company + admin account.
     */
    public function createCompany(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'subscription_months' => 'required|integer|min:1|max:60',
            'subscription_plan' => 'required|string',
            'user_name' => 'required|string',
            'user_email' => 'required|email|unique:users,email',
            'user_password' => 'required|string|min:6',
        ]);

        $slug = Str::slug($request->name) . '-' . rand(100, 999);

        $startsAt = Carbon::now();
        $expiresAt = Carbon::now()->addMonths((int) $request->subscription_months);

        $company = Company::create([
            'name' => $request->name,
            'slug' => $slug,
            'phone' => $request->phone,
            'whatsapp_number' => $request->whatsapp_number,
            'email' => $request->email,
            'address' => $request->address,
            'google_maps_url' => $request->google_maps_url,
            'working_hours' => $request->working_hours,
            'status' => 'active',
            'subscription_plan' => $request->subscription_plan,
            'subscription_starts_at' => $startsAt,
            'subscription_expires_at' => $expiresAt,
            'settings' => [
                'greeting_message' => "مرحباً بكم في {$request->name}! كيف يمكننا مساعدتك اليوم؟",
                'auto_reply_enabled' => true,
            ],
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => $request->user_name,
            'email' => $request->user_email,
            'password' => Hash::make($request->user_password),
            'role' => 'company_admin',
            'phone' => $request->phone,
            'status' => 'active',
        ]);

        SystemNotification::create([
            'company_id' => $company->id,
            'title' => 'اشتراك شركة جديدة',
            'message' => "تم تسجيل شركة جديدة ({$company->name}) باشتراك لمدة {$request->subscription_months} شهر.",
            'type' => 'success',
        ]);

        return response()->json([
            'message' => 'تم إنشاء الشركة وحساب المشترك بنجاح',
            'company' => $company,
            'user' => $user,
        ], 201);
    }

    /**
     * Toggle company status (Activate / Suspend).
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $newStatus = $company->status === 'active' ? 'suspended' : 'active';
        $company->update(['status' => $newStatus]);

        SystemNotification::create([
            'company_id' => $company->id,
            'title' => $newStatus === 'active' ? 'تفعيل اشتراك شركة' : 'إيقاف اشتراك شركة',
            'message' => "تم تغيير حالة شركة ({$company->name}) إلى: " . ($newStatus === 'active' ? 'نشط' : 'معلق'),
            'type' => $newStatus === 'active' ? 'info' : 'warning',
        ]);

        return response()->json([
            'message' => 'تم تحديث حالة الشركة بنجاح',
            'status' => $newStatus,
            'is_active' => $company->isSubscriptionActive(),
        ]);
    }

    /**
     * Extend company subscription by days/months.
     */
    public function extendSubscription(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'days' => 'required|integer|min:1',
        ]);

        $company = Company::findOrFail($id);

        $currentExpiry = $company->subscription_expires_at && $company->subscription_expires_at->isFuture()
            ? $company->subscription_expires_at
            : Carbon::now();

        $newExpiry = $currentExpiry->copy()->addDays((int) $request->days);

        $company->update([
            'subscription_expires_at' => $newExpiry,
            'status' => 'active',
        ]);

        SystemNotification::create([
            'company_id' => $company->id,
            'title' => 'تمديد الاشتراك',
            'message' => "تم تمديد اشتراك شركة ({$company->name}) لمدة {$request->days} يوم، حتى تاريخ {$newExpiry->format('Y-m-d')}.",
            'type' => 'success',
        ]);

        return response()->json([
            'message' => "تم تمديد الاشتراك بنجاح حتى تاريخ {$newExpiry->format('Y-m-d')}",
            'new_expiry' => $newExpiry->format('Y-m-d'),
            'days_remaining' => (int) Carbon::now()->diffInDays($newExpiry, false),
        ]);
    }

    /**
     * Delete company.
     */
    public function deleteCompany(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $name = $company->name;
        $company->delete();

        return response()->json([
            'message' => "تم حذف شركة ({$name}) بنجاح",
        ]);
    }

    /**
     * Notifications list.
     */
    public function notifications(): JsonResponse
    {
        $notifications = SystemNotification::with('company')->latest()->take(50)->get();

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markNotificationRead(int $id): JsonResponse
    {
        $notif = SystemNotification::findOrFail($id);
        $notif->update(['is_read' => true]);

        return response()->json(['message' => 'تم التحديث']);
    }
}