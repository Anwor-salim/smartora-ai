<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class KnowledgeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'title',
        'file_type',
        'file_path',
        'file_size',
        'extracted_text',
        'extracted_data',
        'total_items',
        'status',
        'error_message',
    ];

    protected $casts = [
        'extracted_data' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (KnowledgeDocument $doc): void {
            if ($doc->company_id && ($company = Company::find($doc->company_id))) {
                $company->markKnowledgeUpdated();
            }
        });

        static::deleted(function (KnowledgeDocument $doc): void {
            if (!empty($doc->file_path) && !static::where('file_path', $doc->file_path)->exists()) {
                if (Storage::disk('local')->exists($doc->file_path)) {
                    Storage::disk('local')->delete($doc->file_path);
                }
            }

            if ($doc->company_id && ($company = Company::find($doc->company_id))) {
                $company->markKnowledgeUpdated();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
