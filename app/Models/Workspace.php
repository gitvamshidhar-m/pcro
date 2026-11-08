<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'owner_id', 'settings'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasPublicUlid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withPivot('role')->withTimestamps();
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function crawls(): HasMany
    {
        return $this->hasMany(Crawl::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->owner_id === $user->id || $this->members()->whereKey($user->id)->exists();
    }

    public function roleFor(User $user): ?WorkspaceRole
    {
        if ($this->owner_id === $user->id) {
            return WorkspaceRole::Owner;
        }

        $role = $this->members()->whereKey($user->id)->first()?->pivot?->role;

        return $role ? WorkspaceRole::tryFrom($role) : null;
    }
}
