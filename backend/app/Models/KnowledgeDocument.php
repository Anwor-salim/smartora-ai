<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
