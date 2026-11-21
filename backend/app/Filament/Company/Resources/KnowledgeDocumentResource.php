<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\KnowledgeDocumentResource\Pages;
use App\Models\KnowledgeDocument;
use App\Services\DocumentParserService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class KnowledgeDocumentResource extends Resource
{
    protected static ?string $model = KnowledgeDocument::class;

    protected static ?string $modelLabel = 'ملف معرفة وأسعار';
    protected static ?string $navigationLabel = 'ملفات المنتجات والأسعار (Excel / PDF)';
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        if ($user && ! $user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id ?: 0);
        } elseif ($user && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('الشركة التابع لها المستند')
                        ->relationship('company', 'name')
                        ->default(fn () => auth()->user()?->company_id ?? \App\Models\Company::first()?->id)
                        ->required()
                        ->visible(fn () => auth()->user()?->isSuperAdmin() || ! auth()->user()?->company_id),

                    Forms\Components\TextInput::make('title')
                        ->label('عنوان / وصف الملف')
                        ->placeholder('مثال: قائمة أسعار قطع غيار تويوتا 2026')
                        ->required(),

                    Forms\Components\FileUpload::make('file_path')
                        ->label('ملف الأسعار أو المستند (Excel / CSV / PDF)')
                        ->disk('local')
                        ->directory('knowledge_documents')
                        ->preserveFilenames()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'application/excel',
                            'application/x-excel',
                            'application/x-msexcel',
                            'text/csv',
                            'text/plain',
                            'application/pdf',
                            'application/octet-stream',
                        ])
                        ->maxSize(51200)
                        ->helperText('يدعم صيغ .xlsx و .csv و .pdf. سيقوم النظام فورياً باستخراج الأصناف والأسعار والجداول.')
                        ->required()
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('اسم الملف / الوصف')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (KnowledgeDocument $record) => $record->file_path),

                Tables\Columns\TextColumn::make('file_type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state ?? 'FILE'))
                    ->color(fn ($state) => match (strtolower($state ?? '')) {
                        'xlsx', 'xls', 'csv' => 'success',
                        'pdf' => 'danger',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('total_items')
                    ->label('الأصناف المستخرجة')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => ($state ?? 0) . ' صنف / سجل'),

                Tables\Columns\TextColumn::make('status')
                    ->label('حالة المعالجة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ready' => 'جاهز للاستخدام',
                        'processing' => 'قيد المعالجة',
                        'error' => 'تعذر التحليل',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ready' => 'success',
                        'processing' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('file_size')
                    ->label('حجم الملف')
                    ->formatStateUsing(function ($state) {
                        if (! $state) return '-';
                        return round($state / 1024, 1) . ' KB';
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الرفع')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('file_type')
                    ->label('نوع الملف')
                    ->options([
                        'xlsx' => 'Excel (.xlsx)',
                        'csv' => 'CSV',
                        'pdf' => 'PDF',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('preview_data')
                    ->label('معاينة المحتوى المستخرج')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (KnowledgeDocument $record) => 'المحتوى المستخرج من: ' . $record->title)
                    ->modalContent(fn (KnowledgeDocument $record) => view('filament.components.document-preview', ['document' => $record])),

                Tables\Actions\Action::make('reparse')
                    ->label('إعادة التحليل')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (KnowledgeDocument $record) {
                        try {
                            $fullPath = Storage::disk('local')->path($record->file_path);
                            $ext = pathinfo($record->file_path, PATHINFO_EXTENSION);
                            $parser = app(DocumentParserService::class);
                            $parsed = $parser->parseFile($fullPath, $ext);

                            $record->update([
                                'extracted_text' => $parsed['text'],
                                'extracted_data' => $parsed['data'],
                                'total_items' => $parsed['total_items'] ?? count($parsed['data']),
                                'status' => 'ready',
                                'error_message' => null,
                            ]);

                            Notification::make()->title('تمت إعادة تحليل الملف بنجاح!')->success()->send();
                        } catch (\Throwable $e) {
                            $record->update(['status' => 'error', 'error_message' => $e->getMessage()]);
                            Notification::make()->title('فشل تحليل الملف: ' . $e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKnowledgeDocuments::route('/'),
            'create' => Pages\CreateKnowledgeDocument::route('/create'),
        ];
    }
}
