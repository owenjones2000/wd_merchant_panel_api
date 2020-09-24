<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationLog extends Model
{
    protected $table = 'ua_operation_logs';
    protected $fillable = ['main_user_id', 'user_id','username','realname','ip','method','uri','query'];
}
