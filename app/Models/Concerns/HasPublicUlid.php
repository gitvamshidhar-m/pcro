<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasPublicUlid
{
    protected static function bootHasPublicUlid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function initializeHasPublicUlid(): void
    {
        if (! isset($this->attributes['ulid']) || $this->attributes['ulid'] === null || $this->attributes['ulid'] === '') {
            $this->attributes['ulid'] = (string) Str::ulid();
        }
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
