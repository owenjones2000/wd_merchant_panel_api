<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Class User
 *
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Role[] $roles
 *
 * @method static User create(array $user)
 * @package App
 */
class User extends Authenticatable implements JWTSubject
{
    use Notifiable, HasRoles, SoftDeletes;

    protected $table = 'a_users';

    /**
     * Set permissions guard to API by default
     * @var string
     */
    protected $guard_name = 'api';

    /**
     * @inheritdoc
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * @inheritdoc
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * @return bool
     */
    public function isAdmin(): bool
    {
        foreach ($this->roles  as $role) {
            if ($role->isAdmin()) {
                return true;
            }
        }

        return false;
    }

    public function getMainId()
    {
        return $this->main_user_id; // > 0 ? $this->main_user_id : $this->id;
    }

    public function isMainAccount()
    {
        return empty($this->main_user_id);
    }

    /**
     * 广告主
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function mainUsers()
    {
        return $this->belongsToMany(
            User::class,
            'a_users_advertiser',
            'advertiser_user_id',
            'main_user_id',
            'id',
            'id'
        );
    }

    /**
     * 广告主
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function activeMainUsers()
    {
        return $this->mainUsers()
            ->where(function ($query) {
                $query->where('isAdvertiseEnabled', 1)
                    ->orWhere('isPublishEnabled', 1);
            });
    }

    /**
     * 当前广告主
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currentMainUser()
    {
        return $this->belongsTo(User::class, 'main_user_id', 'id');
    }

    /**
     * 广告人员
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function advertisers()
    {
        return $this->belongsToMany(
            User::class,
            'a_users_advertiser',
            'main_user_id',
            'advertiser_user_id',
            'id',
            'id'
        );
    }

    /**
     * A model may have multiple direct permissions.
     */
    public function permissions($main_user_id = null): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.permission'),
            'model',
            config('permission.table_names.model_has_permissions'),
            config('permission.column_names.model_morph_key'),
            'permission_id'
        )
            ->wherePivot('main_user_id', $main_user_id ?: $this->getMainId());
    }

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username', 'realname', 'email', 'password_hash'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password_hash', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
