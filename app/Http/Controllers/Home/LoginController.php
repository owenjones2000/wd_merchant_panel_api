<?php

namespace App\Http\Controllers\Home;
use App\Models\Site;
use App\Models\LoginLog;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * 登录表单
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showLoginForm()
    {
        //var_dump(Hash::make('123456'));die;
        return view('home.login_register.login');
    }

    /**
     * 用于登录的字段
     * @return string
     */
//    public function username()
//    {
//        return 'username';
//    }

    /**
     * 登录成功后的跳转地址
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function redirectTo()
    {
        return route('home.layout');
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->invalidate();

        return redirect(route('home.login'));
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard();
    }
    protected function validateLogin(Request $request){
        $this->validate($request, [
            $this->username() => 'required|string',
            'password' => 'required|string',
            ]
        );
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function credentials(Request $request)
    {
        $credentials = $request->only($this->username(), 'password');
        $credentials['status'] = true;
        return $credentials;
    }

    protected function authenticated(Request $request, $user)
    {
        $data = [
            'user_id' => $user->id,
            'username' => $user->username,
            'realname' => $user->realname,
            'ip' => $request->getClientIp()
        ];
        LoginLog::create($data);
    }

}
