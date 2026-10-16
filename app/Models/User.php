<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->groups()
            ->whereHas('permissions', fn (Builder $query) => $query->where('slug', $permission))
            ->exists();
    }

    protected function performInsert(Builder $query)
    {
        // Keep insertion and membership atomic, including quiet saves that skip model events.
        return $this->getConnection()->transaction(function () use ($query) {
            $state = $this->getConnection()->table('access_control_state')
                ->where('id', 1)->lockForUpdate()->first();

            if ($state === null) {
                throw new LogicException('Access control is not initialized. Run migrations and seeders.');
            }

            $slug = $state->first_user_assigned ? Group::GUEST : Group::EXECUTIVE_DIRECTOR;
            $group = Group::on($this->getConnectionName())->where('slug', $slug)->first();

            if ($group === null) {
                throw new LogicException('Default group missing. Run php artisan db:seed before creating users.');
            }

            if (! parent::performInsert($query)) {
                return false;
            }

            $this->groups()->attach($group);
            $this->getConnection()->table('access_control_state')
                ->where('id', 1)->update(['first_user_assigned' => true]);

            return true;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
