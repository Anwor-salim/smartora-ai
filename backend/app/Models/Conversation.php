<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'channel',
        'customer_phone',
        'customer_name',
        'last_message',
        'last_interaction_at',
        'status',
        'conversation_state',
        'context_data',
        'summary',
        'ai_paused',
        'ai_paused_at',
    ];

    protected $casts = [
        'last_interaction_at' => 'datetime',
        'context_data' => 'array',
        'ai_paused' => 'boolean',
        'ai_paused_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isAiPaused(): bool
    {
        return (bool) $this->ai_paused;
    }

    /**
     * Retrieve recent conversation messages in strict chronological order.
     */
    public function getRecentHistory(int $limit = 12, int $windowHours = 24, ?int $excludeMessageId = null, ?int $minMessageId = null): Collection
    {
        $query = $this->messages()
            ->where('created_at', '>=', Carbon::now()->subHours($windowHours));

        if ($minMessageId !== null && $minMessageId > 0) {
            $query->where('id', '>', $minMessageId);
        }

        if ($excludeMessageId !== null) {
            $query->where('id', '!=', $excludeMessageId);
        }

        return $query
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Reset conversation memory, state, and pause status.
     */
    public function resetContext(): void
    {
        $this->update([
            'conversation_state' => 'browsing',
            'context_data' => null,
            'summary' => null,
            'ai_paused' => false,
            'ai_paused_at' => null,
        ]);
    }
}
