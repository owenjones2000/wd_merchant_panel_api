<?php

namespace App\Models\Advertise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AppTag extends Model
{
    //
    protected $table= 'a_app_tag';
    protected $fillable = [
        'name',
        'status', 
    ];

    public static function Make($params)
    {
        $apps = DB::transaction(function () use ($params) {
            if (empty($params['id'])) {
                $apps = new self();
            } else {
                $apps = self::query()->where([
                    'id' => $params['id'],
                ])->firstOrFail();
            }

            $apps->fill($params);
            $apps->saveOrFail();

            return $apps;
        }, 3);
        return $apps;
    }

}
