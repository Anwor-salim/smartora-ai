<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\ConversationResource\Pages;
use App\Models\Conversation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static ?string $modelLabel = 'محادثة عميل';
    protected static ?string $pluralModelLabel = 'محادثات العملاء';
    protected static ?string $navigationLabel = 'محادثات العملاء';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?int $navigationSort = 5;

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
                    Forms\Components\Grid::make(2)->schema([
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

                Forms\Components\Section::make('سجل الرسائل الكامل مع البوت')
                    ->schema([
                        Forms\Components\Repeater::make('messages')
                            ->relationship('messages')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Select::make('direction')
                                        ->label('المرسل')
                                        ->options([
                                            'inbound' => 'رسالة العميل 👤',
                                            'outbound' => 'رد البوت الذكي 🤖',
                                        ])
                                        ->disabled(),

                                    Forms\Components\TextInput::make('created_at')
                                        ->label('التاريخ والوقت')
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
                    ->label('رقم هاتف العميل')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->icon('heroicon-m-phone'),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('اسم العميل')
                    ->placeholder('مجهول')
                    ->searchable(),

                Tables\Columns\TextColumn::make('last_message')
                    ->label('آخر رسالة')
                    ->limit(50)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('last_interaction_at')
                    ->label('آخر تواصل')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (Conversation $record) => $record->last_interaction_at?->diffForHumans()),

                Tables\Columns\TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label('الرسائل')
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('last_interaction_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض المحادثة'),
            ])
            ->bulkActions([]);
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
