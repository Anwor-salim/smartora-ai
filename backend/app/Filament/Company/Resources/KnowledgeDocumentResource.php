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
    protected static ?string $pluralModelLabel = 'قاعدة المعرفة والأسعار';
    protected static ?string $navigationLabel = 'قاعدة المعرفة';
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
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('الملف')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(function (KnowledgeDocument $record): string {
                        $filename = basename((string) $record->file_path);
                        $size = $record->file_size ? round($record->file_size / 1024, 1) . ' KB' : '';

                        return $size ? "{$filename} · {$size}" : $filename;
                    }),

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
                    ->label('الأصناف')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => ($state ?? 0) . ' صنف'),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ready' => 'جاهز',
                        'processing' => 'قيد المعالجة',
                        'error' => 'خطأ بالتحليل',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ready' => 'success',
                        'processing' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الرفع')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (KnowledgeDocument $record) => $record->created_at?->format('d/m/Y H:i')),
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
                    ->label('معاينة')
                    ->button()
                    ->size('xs')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (KnowledgeDocument $record) => 'المحتوى المستخرج من: ' . $record->title)
                    ->modalContent(fn (KnowledgeDocument $record) => view('filament.components.document-preview', ['document' => $record])),

                Tables\Actions\ActionGroup::make([
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

                    Tables\Actions\DeleteAction::make()
                        ->label('حذف الملف'),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('خيارات'),
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
