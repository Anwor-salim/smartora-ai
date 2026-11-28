<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    public const ACTIVE_STATUSES = ['pending', 'approved'];

    protected $fillable = [
        'company_id',
        'conversation_id',
        'booking_code',
        'channel',
        'customer_phone',
        'customer_name',
        'appointment_date',
        'appointment_time',
        'queue_number',
        'form_data',
        'status',
        'rejection_reason',
        'sheet_row_number',
        'sheet_synced_at',
        'customer_notified_at',
        'notified_status',
        'approved_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'form_data' => 'array',
        'sheet_synced_at' => 'datetime',
        'customer_notified_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function statusLabelArabic(): string
    {
        return match ($this->status) {
            'approved' => 'مؤكد',
            'rejected' => 'مرفوض',
            'cancelled' => 'ملغي',
            'completed' => 'مكتمل',
            default => 'قيد المراجعة',
        };
    }
}
