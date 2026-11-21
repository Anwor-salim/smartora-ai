<?php

namespace App\Filament\Company\Resources\KnowledgeDocumentResource\Pages;

use App\Filament\Company\Resources\KnowledgeDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKnowledgeDocument extends EditRecord
{
    protected static string $resource = KnowledgeDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
