<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function success($data = [])
    {
        $ret = [
            "code" => 0,
            "data" => $data,
            "message" => "success"
        ];
        return $ret;
    }

    public function fail($code = 100, $data = [], $message = '')
    {
        $ret = [
            "code" => $code,
            "data" => $data,
            "message" => $message
        ];
        return $ret;
    }
}
