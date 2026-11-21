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
    protected static ?string $navigationLabel = 'محادثات الواتساب';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('المنشأة')
                            ->relationship('company', 'name')
                            ->disabled(),

                        Forms\Components\TextInput::make('customer_phone')
                            ->label('رقم هاتف العميل')
                            ->disabled(),

                        Forms\Components\TextInput::make('customer_name')
                            ->label('اسم العميل')
                            ->disabled(),
                    ]),

                    Forms\Components\Textarea::make('last_message')
                        ->label('آخر رسالة متبادلة')
                        ->disabled()
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

                Forms\Components\Section::make('الرسائل المتبادلة')
                    ->schema([
                        Forms\Components\Repeater::make('messages')
                            ->relationship('messages')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Select::make('direction')
                                        ->label('الطرف')
                                        ->options([
                                            'inbound' => 'العميل',
                                            'outbound' => 'البوت الذكي',
                                        ])
                                        ->disabled(),

                                    Forms\Components\TextInput::make('created_at')
                                        ->label('الوقت والتاريخ')
                                        ->disabled(),

                                    Forms\Components\Textarea::make('content')
                                        ->label('نص الرسالة')
                                        ->rows(2)
                                        ->disabled()
                                        ->columnSpanFull(),
                                ]),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('رقم العميل')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->icon('heroicon-m-phone'),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('اسم العميل')
                    ->placeholder('مجهول')
                    ->searchable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('المنشأة')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('last_message')
                    ->label('آخر رسالة')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('last_interaction_at')
                    ->label('آخر تفاعل')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (Conversation $record) => $record->last_interaction_at?->diffForHumans()),

                Tables\Columns\TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label('عدد الرسائل')
                    ->badge()
                    ->color('primary'),
            ])
            ->defaultSort('last_interaction_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('فلترة حسب المنشأة')
                    ->relationship('company', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض المحادثة'),
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
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }
}
