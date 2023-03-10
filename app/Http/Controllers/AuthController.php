<?php

/**
 * File AuthController.php
 *
 * @author Tuan Duong <bacduong@gmail.com>
 * @package Laravue
 * @version 1.0
 */

namespace App\Http\Controllers;

use App\Laravue\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Log;

/**
 * Class AuthController
 *
 * @package App\Http\Controllers
 */
class AuthController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        if ($token = $this->guard()->attempt($credentials)) {
            Log::info('login user' . Auth::user()['realname']);
            return response()->json(
                [
                    'code' => 0,
                    'data' => new UserResource(Auth::user())
                ],
                Response::HTTP_OK
            )->header('Authorization', $token);
        }

        return $this->fail(1000, [], 'login error');
    }

    public function logout()
    {
        $this->guard()->logout();
        return response()->json((new JsonResponse())->success([]), Response::HTTP_OK);
    }

    public function user()
    {
        $user = Auth::user();
        return response()->json(
            [
                'code' => 0,
                'data' => new UserResource($user)
            ]
        );
    }

    /**
     * @return mixed
     */
    private function guard()
    {
        return Auth::guard();
    }
}
