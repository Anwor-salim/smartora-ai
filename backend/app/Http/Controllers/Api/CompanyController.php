<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Services\DocumentParserService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function __construct(
        protected DocumentParserService $parserService
    ) {}

    /**
     * Get Company Profile & Settings.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['message' => 'لا توجد شركة مرتبطة بهذا الحساب'], 404);
        }

        $isExpired = $company->subscription_expires_at && $company->subscription_expires_at->isPast();

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'phone' => $company->phone,
                'whatsapp_number' => $company->whatsapp_number,
                'email' => $company->email,
                'address' => $company->address,
                'google_maps_url' => $company->google_maps_url,
                'working_hours' => $company->working_hours,
                'custom_system_prompt' => $company->custom_system_prompt,
                'status' => $company->status,
                'is_active' => $company->isSubscriptionActive(),
                'is_expired' => $isExpired,
                'subscription_plan' => $company->subscription_plan,
                'subscription_expires_at' => $company->subscription_expires_at?->format('Y-m-d'),
                'days_remaining' => $company->subscription_expires_at ? max(0, (int) Carbon::now()->diffInDays($company->subscription_expires_at, false)) : null,
                'settings' => $company->settings,
            ],
            'stats' => [
                'documents_count' => $company->documents()->count(),
                'conversations_count' => $company->conversations()->count(),
            ],
        ]);
    }

    /**
     * Update Company Profile, WhatsApp number, and Prompts.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['message' => 'لا توجد شركة مرتبطة بهذا الحساب'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'google_maps_url' => 'nullable|string',
            'working_hours' => 'nullable|string',
            'custom_system_prompt' => 'nullable|string',
            'greeting_message' => 'nullable|string',
        ]);

        $settings = $company->settings ?: [];
        if ($request->has('greeting_message')) {
            $settings['greeting_message'] = $request->greeting_message;
        }

        $company->update([
            'name' => $request->name ?? $company->name,
            'phone' => $request->phone ?? $company->phone,
            'whatsapp_number' => $request->whatsapp_number ?? $company->whatsapp_number,
            'email' => $request->email ?? $company->email,
            'address' => $request->address ?? $company->address,
            'google_maps_url' => $request->google_maps_url ?? $company->google_maps_url,
            'working_hours' => $request->working_hours ?? $company->working_hours,
            'custom_system_prompt' => $request->custom_system_prompt ?? $company->custom_system_prompt,
            'settings' => $settings,
        ]);

        return response()->json([
            'message' => 'تم حفظ وتحديث إعدادات الشركة بنجاح',
            'company' => $company->fresh(),
        ]);
    }

    /**
     * List uploaded documents.
     */
    public function documents(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['documents' => []]);
        }

        $documents = $company->documents()->latest()->get()->map(function ($doc) {
            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'file_type' => $doc->file_type,
                'file_size' => $doc->file_size,
                'formatted_size' => round($doc->file_size / 1024, 1) . ' KB',
                'total_items' => $doc->total_items,
                'status' => $doc->status,
                'created_at' => $doc->created_at->format('Y-m-d H:i'),
                'preview_data' => array_slice($doc->extracted_data ?: [], 0, 10),
            ];
        });

        return response()->json(['documents' => $documents]);
    }

    /**
     * Upload and Parse Excel / PDF Document.
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,pdf,txt|max:20480', // max 20MB
            'title' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $originalName = $file->getClientOriginalName();
        $title = $request->title ?: pathinfo($originalName, PATHINFO_FILENAME);

        $path = $file->store('company_knowledge/' . $company->id, 'local');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $parsed = $this->parserService->parseFile($fullPath, $extension);

            $document = KnowledgeDocument::create([
                'company_id' => $company->id,
                'title' => $title,
                'file_type' => strtolower($extension),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'extracted_text' => $parsed['text'],
                'extracted_data' => $parsed['data'],
                'total_items' => $parsed['total_items'],
                'status' => 'ready',
            ]);

            return response()->json([
                'message' => 'تم رفع الملف ومعالجة البيانات بنجاح، وأصبح الذكاء الاصطناعي قادراً على الإجابة منه فوراً!',
                'document' => $document,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'فشلت معالجة الملف: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a Document.
     */
    public function deleteDocument(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        $doc = KnowledgeDocument::where('company_id', $company->id)->findOrFail($id);

        if (Storage::disk('local')->exists($doc->file_path)) {
            Storage::disk('local')->delete($doc->file_path);
        }

        $doc->delete();

        return response()->json(['message' => 'تم حذف المستند من قاعدة المعرفة بنجاح']);
    }

    /**
     * Customer Conversations list.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['conversations' => []]);
        }

        $conversations = $company->conversations()
            ->withCount('messages')
            ->latest('last_interaction_at')
            ->get();

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Conversation Detail & Chat History.
     */
    public function conversationDetail(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        $conversation = Conversation::where('company_id', $company->id)
            ->with('messages')
            ->findOrFail($id);

        return response()->json(['conversation' => $conversation]);
    }
}