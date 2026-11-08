<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'crawl_id', 'status', 'disk_path', 'generated_at', 'meta'])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function existsOnDisk(): bool
    {
        return $this->disk_path !== null && file_exists(storage_path('app/'.$this->disk_path));
    }
}
