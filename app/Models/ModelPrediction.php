<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'website_id', 'subject_type', 'subject_id', 'model_name',
    'model_version', 'prediction', 'score', 'predicted_at',
])]
class ModelPrediction extends Model
{
    use HasPublicUlid;

    protected function casts(): array
    {
        return [
            'prediction' => 'array',
            'score' => 'float',
            'predicted_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
