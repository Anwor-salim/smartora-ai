<?php

namespace App\Filament\Company\Resources\KnowledgeDocumentResource\Pages;

use App\Filament\Company\Resources\KnowledgeDocumentResource;
use App\Models\SystemNotification;
use App\Services\DocumentParserService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateKnowledgeDocument extends CreateRecord
{
    protected static string $resource = KnowledgeDocumentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $companyId = $user?->company_id ?? \App\Models\Company::latest()->first()?->id;

        if (! $companyId) {
            Notification::make()->title('يجب إضافة شركة أولاً قبل رفع المستندات')->danger()->send();
            throw new \Exception('No company found to associate this document with.');
        }
        $filePath = $data['file_path'];
        $fullPath = Storage::disk('local')->path($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

        $extractedText = '';
        $extractedData = [];
        $totalItems = 0;
        $status = 'ready';
        $errorMessage = null;

        try {
            $parser = app(DocumentParserService::class);
            $parsed = $parser->parseFile($fullPath, $extension);
            $extractedText = $parsed['text'] ?? '';
            $extractedData = $parsed['data'] ?? [];
            $totalItems = $parsed['total_items'] ?? count($extractedData);
        } catch (\Throwable $e) {
            $status = 'error';
            $errorMessage = $e->getMessage();
        }

        $record = static::getModel()::create([
            'company_id' => $companyId,
            'title' => $data['title'],
            'file_type' => $extension,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'extracted_text' => $extractedText,
            'extracted_data' => $extractedData,
            'total_items' => $totalItems,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        SystemNotification::create([
            'company_id' => $companyId,
            'title' => 'تم رفع ملف معرفة جديد',
            'message' => "تمت معالجة الملف '{$data['title']}' واستخراج {$totalItems} صنف بنجاح.",
            'type' => 'info',
        ]);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
