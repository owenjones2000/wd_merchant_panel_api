<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $table = 'ua_login_logs';
    protected $fillable = ['user_id','username','realname','ip'];
}
