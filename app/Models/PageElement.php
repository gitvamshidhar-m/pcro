<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\PageElementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'page_id', 'type', 'tag', 'text', 'selector', 'attributes', 'position'])]
class PageElement extends Model
{
    /** @use HasFactory<PageElementFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'position' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
