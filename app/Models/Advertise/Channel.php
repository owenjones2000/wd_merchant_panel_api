<?php
namespace App\Models\Advertise;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Channel extends Model
{
    protected $table = 'a_target_apps';

    protected $fillable = ['name', 'icon_url', 'bundle_id',
        'platform', 'put_mode', 'status'];

    /**
     * 构造Channel
     * @param User $user
     * @param $params
     * @return mixed
     */
    public static function Make($user, $params){
        $apps = DB::transaction(function () use($user, $params) {
            $main_user_id = $user->getMainId();
            if (empty($params['id'])) {
                $apps = new self();
                $apps->main_user_id = $main_user_id;
                $apps['status'] = true;
            } else {
                $apps = self::query()->where([
                    'id' => $params['id'],
                    'main_user_id' => $main_user_id
                ])->firstOrFail();
            }
            $apps->fill($params);
            if(empty($apps['name_hash'])){
                $apps['name_hash'] = md5($apps['bundle_id'].$apps['name'].$apps['platform']);
            }
            $apps->saveOrFail();

            return $apps;
        }, 3);
        return $apps;
    }

    /**
     * 启用
     * @throws \Throwable
     */
    public function enable(){
        if(!$this->status){
            $this->status = true;
            $this->saveOrFail();
        }
    }

    /**
     * 停用
     * @throws \Throwable
     */
    public function disable(){
        if($this->status){
            $this->status = false;
            $this->saveOrFail();
        }
    }
}