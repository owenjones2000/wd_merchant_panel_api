<?php

namespace App\Models\Advertise;

use App\Scopes\TenantScope;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class App extends Model
{
    use SoftDeletes;

    protected $table = 'a_app';
    protected $appends = ['track'];
    const  App_Type_Shop = 1;
    const  App_Type_Apk = 2;
    protected $casts =  [
        'extra_data' => 'array',
    ];

    protected $fillable = [
        'name', 'description',
        'icon_url', 'bundle_id',
        'os',
        'track_platform_id', 'track_code', 'track_url',
        'status',
        'app_id',
        'type',
        'extra_data'
    ];

    /**
     * 构造App
     * @param User $user
     * @param $params
     * @return mixed
     */
    public static function Make($user, $params)
    {
        $apps = DB::transaction(function () use ($user, $params) {
            $main_user_id = $user->getMainId();
            // if (isset($params['type']) && $params['type']  == 1){
                
            // }
            if (empty($params['id'])) {
                $apps = new self();
                $apps->main_user_id = $main_user_id;
                $apps->is_admin_disable = true;
                $apps['status'] = false;
                if ($params['track_platform_id'] == TrackPlatform::Adjust && empty($params['track_code'])) {
                    throw new \Exception('Track code required.');
                }
            } else {
                $apps = self::query()->where([
                    'id' => $params['id'],
                    'main_user_id' => $main_user_id
                ])->firstOrFail();
                unset(
                    $params['extra_data']['land_page'], 
                    $params['extra_data']['apk_page'], 
                    $params['type']
                );
                $params['extra_data'] = array_merge($apps->extra_data, $params['extra_data']);
            }
            
            $apps->fill($params);
            $apps->saveOrFail();
            $tags = [];
            if ($params['tags'] ?? null){
                $tags = explode(',', $params['tags']);
                $apps->tags()->sync($tags);
            }
            return $apps;
        }, 3);
        return $apps;
    }

    /**
     * 启用
     * @throws \Throwable
     */
    public function enable()
    {
        if (!$this->status) {
            $this->status = true;
            $this->saveOrFail();
        }
    }

    /**
     * 停用
     * @throws \Throwable
     */
    public function disable()
    {
        if ($this->status) {
            $this->status = false;
            $this->saveOrFail();
        }
    }

    public function ads()
    {
        return $this->hasMany(Ad::class, 'app_id', 'id');
    }

    public function tags()
    {
        return $this->belongsToMany(AppTag::class, 'a_app_tags', 'app_id', 'tag_id', 'id', 'id');
    }

    public function getTrackAttribute()
    {
        return TrackPlatform::get($this['track_platform_id']);
    }

    /**
     * 禁用投放渠道
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function disableChannels()
    {
        return $this->belongsToMany(
            Channel::class,
            'a_app_target_app_disabled',
            'app_id',
            'target_app_id',
            'id',
            'id'
        );
    }

    /**
     *  模型的 「启动」 方法.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantScope(Auth::user()->getMainId()));
    }
}
