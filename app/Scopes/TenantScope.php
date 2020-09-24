<?php
/**
 * 分租户范围
 * User: Dev
 * Date: 2019/11/27
 * Time: 14:19
 */

namespace App\Scopes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * 把约束加到 Eloquent 查询构造中。
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->where('main_user_id', $this->main_user_id);
    }

    public function __construct($main_user_id)
    {
        $this->main_user_id = $main_user_id;
    }

    private $main_user_id;
}