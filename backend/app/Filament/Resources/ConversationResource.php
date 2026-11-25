<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversationResource\Pages;
use App\Models\Conversation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static ?string $modelLabel = 'محادثة عميل';
    protected static ?string $pluralModelLabel = 'سجل محادثات العملاء';
    protected static ?string $navigationLabel = 'سجل المحادثات';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات المحادثة')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\Select::make('company_id')
                                ->label('المنشأة')
                                ->relationship('company', 'name')
                                ->disabled(),

                            Forms\Components\Select::make('channel')
                                ->label('القناة')
                                ->options([
                                    'whatsapp' => 'واتساب',
                                    'instagram' => 'انستقرام',
                                ])
                                ->disabled(),

                            Forms\Components\TextInput::make('customer_phone')
                                ->label('رقم / معرّف العميل')
                                ->disabled(),

                            Forms\Components\TextInput::make('customer_name')
                                ->label('اسم العميل')
                                ->disabled(),
                        ]),
                    ]),

                Forms\Components\ViewField::make('messages_thread')
                    ->view('filament.components.conversation-messages')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('channel')
                    ->label('القناة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'instagram' ? 'انستقرام' : 'واتساب')
                    ->color(fn (?string $state): string => $state === 'instagram' ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('العميل')
                    ->formatStateUsing(function (?string $state, Conversation $record): string {
                        $cleanId = str_starts_with((string) $state, 'ig:') ? substr((string) $state, 3) : (string) $state;

                        return $record->customer_name ?: $cleanId;
                    })
                    ->description(function (Conversation $record): ?string {
                        if (! $record->customer_name) {
                            return null;
                        }

                        return str_starts_with((string) $record->customer_phone, 'ig:')
                            ? substr((string) $record->customer_phone, 3)
                            : (string) $record->customer_phone;
                    })
                    ->searchable(['customer_phone', 'customer_name'])
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('المنشأة')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('last_message')
                    ->label('آخر رسالة')
                    ->limit(38)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label('الرسائل')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('last_interaction_at')
                    ->label('آخر تفاعل')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (Conversation $record) => $record->last_interaction_at?->format('d/m/Y H:i')),
            ])
            ->defaultSort('last_interaction_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->label('القناة')
                    ->options([
                        'whatsapp' => 'واتساب',
                        'instagram' => 'انستقرام',
                    ]),
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('فلترة حسب المنشأة')
                    ->relationship('company', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->tooltip('عرض المحادثة'),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('حذف'),
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
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }
}
