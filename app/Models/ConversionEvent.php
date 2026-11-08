<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'website_id', 'tracking_session_id', 'event_name',
    'value', 'currency', 'properties', 'occurred_at',
])]
class ConversionEvent extends Model
{
    use HasPublicUlid;

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'value' => 'float',
            'occurred_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
