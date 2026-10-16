<?php

namespace Kevinbharris1991\LaravelAccessControlSystem\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['slug', 'name'];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }
}
