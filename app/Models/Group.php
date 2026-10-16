<?php

namespace Kevinbharris1991\LaravelAccessControlSystem\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    public const EXECUTIVE_DIRECTOR = 'executive-director';

    public const GUEST = 'guest';

    protected $fillable = ['slug', 'name', 'focus'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
