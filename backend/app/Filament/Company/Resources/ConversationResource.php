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
                Forms\Components\Section::make('بيانات العميل والقناة')
                    ->compact()
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
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

                Tables\Columns\TextColumn::make('last_message')
                    ->label('آخر رسالة')
                    ->limit(38)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label('الرسائل')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('last_interaction_at')
                    ->label('آخر تواصل')
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->tooltip('عرض المحادثة'),
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
