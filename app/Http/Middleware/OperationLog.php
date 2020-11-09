<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\User;
use Closure;

class OperationLog
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // TODO::改为配置
        if (1){
            /** @var User $op_user */
            $op_user = auth()->user();
            $data = [
                'main_user_id' => $op_user->getMainId(),
                'user_id' => auth()->id(),
                'username' => $op_user->username,
                'realname' => $op_user->realname,
                'ip' => isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : $request->getClientIp(),
                'method' => $request->method(),
                'uri' => $request->path(),
                'query' => 'V2'.http_build_query($request->except(['password','_token'])),
            ];
            \App\Models\OperationLog::create($data);
        }
        return $next($request);
    }
}
