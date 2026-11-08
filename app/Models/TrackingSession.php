<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'website_id', 'session_key', 'visitor_hash', 'landing_page',
    'referrer', 'utm', 'device', 'consent', 'started_at', 'ended_at',
])]
class TrackingSession extends Model
{
    use HasPublicUlid;

    protected function casts(): array
    {
        return [
            'utm' => 'array',
            'device' => 'array',
            'consent' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
