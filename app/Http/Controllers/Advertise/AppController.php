<?php

namespace App\Http\Controllers\Advertise;

use App\Exceptions\BizException;
use App\Models\Advertise\App;
use App\Rules\AdvertiseName;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Advertise\AppTag;
use App\Models\Advertise\TrackPlatform;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class AppController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('advertise.app.list');
    }

    public function data(Request $request)
    {
        $app_query = App::query()->where('main_user_id', Auth::user()->getMainId());
        if(!empty($request->get('keyword'))){
            $like_keyword = '%'.$request->get('keyword').'%';
            $app_query->where('name', 'like', $like_keyword);
        }
        $res = $app_query->orderBy($request->get('field','status'),$request->get('order','desc'))
            ->orderBy('id','desc')
            ->paginate($request->get('limit',30));

        return $this->success($res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id = null)
    {
        if(empty($id)){
            $apps = new App();
        }else{
            /** @var App $apps */
            $apps = App::findOrFail($id);
        }
        $tags = AppTag::where('status', 1)->select([
            'id',
            'name'
        ])
        ->get();
        $apptags = $apps->tags()->pluck('id');
        return view('advertise.app.edit',compact('apps', 'tags', 'apptags'));
    }

    public function tags()
    {
        $tags = AppTag::where('status', 1)->where('group', '!=', 0)->select([
            'id',
            'name'
        ])
        ->get();
        return $this->success($tags);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function save(Request $request)
    {
        $this->validate($request,[
            'name'  => [
                'required',
                'string',
                // 'unique:a_app,name',
                function ($attribute, $value, $fail) use ($request){
                    $taken = App::where('name', $value)->where('os', $request->input('os'))->first();
                    if ($taken) {
                        $fail($attribute.' has been taken.');
                    }
                },
                new AdvertiseName()
            ],
            'bundle_id'  => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $taken = App::where('bundle_id', $value)->where('os', $request->input('os'))->first();
                    if ($taken) {
                        $fail($attribute . ' has been taken.');
                    }
                },
            ],
            'description' => 'string|max:200',
            'icon_url' => 'string|max:200',
            'track_url' => 'string|required',
            'track_platform_id' => 'numeric|required',
            'track_code' => 'string|required',
            'app_id' => 'string|',
        ]);
        try{
            $params = $request->all();
            if($request->input('os') == 'ios'){
                if (strlen($request->input('app_id')) > 10 || strlen($request->input('app_id')) < 8){
                    return $this->fail(1001, [], 'app_id is wrong');
                }else{
                    $params['app_id'] = 'id'.$params['app_id'];
                }
                
            }
            $this->checkTrackurl($params);
            $params['id'] = null;
            App::Make(Auth::user(), $params);
            return $this->success();
        } catch(\Exception $ex){
            return $this->fail(1001, [], $ex->getMessage());
        }
    }

    public function checkTrackurl($params)
    {
        switch ($params['track_platform_id']) {
            case TrackPlatform::AppsFlyer:
                if(strpos($params['track_url'], "app.appsflyer.com/{$params['track_code']}") === false){
                    throw new \Exception("Error track url", 1000);
                }
                break;
            case TrackPlatform::Adjust:
                if (strpos($params['track_url'], "adjust.com/{$params['track_code']}") === false) {
                    throw new \Exception("Error track url", 1000);
                }
                break;
            case TrackPlatform::Kochava:
                if (strpos($params['track_url'], "control.kochava.com/v1/cpi/click") === false) {
                    throw new \Exception("Error track url", 1000);
                }
                break;
            default:
                throw new \Exception("Error track url", 1000);
                break;
        }
    }

    public function update(Request $request, $id)
    {
        $this->validate($request,[
            'name'  => [
                'required',
                'string',
                // 'unique:a_app,name,'.$id.',id',
                function ($attribute, $value, $fail) use ($request, $id) {
                    $taken = App::where('name', $value)->where('os', $request->input('os'))
                    ->where('id','<>', $id)->first();
                    if ($taken) {
                        $fail($attribute . ' has been taken.');
                    }
                },
                new AdvertiseName()
            ],
            'description' => 'string|max:200',
            'icon_url' => 'string|max:200',
            'track_url' => 'string',
            'app_id' => 'string|',
        ]);
        try{
            $params = $request->all();
            if($request->input('os') == 'ios'){
                if (strlen($request->input('app_id')) > 10 || strlen($request->input('app_id')) < 8){
                    return $this->fail(1001, [], 'app_id is wrong');
                }else{
                    $params['app_id'] = 'id'.$params['app_id'];
                }
                
            }
            $params['id'] = $id;
            $app = App::Make(Auth::user(), $params);
            // $string = view('advertise.app.list')->__toString();
            // $string = view('advertise.app.list')->render();
            // $dir = 'html/';
            // $file_name  = 'app-test.html';
            // dd($string);
            // dump($upload = Storage::put($dir . $file_name, $string));
            // dd($url = Storage::url($dir . $file_name));
            // dump($app);
            return $this->success();
        } catch(\Exception $ex){
            return $this->fail(1001, [], $ex->getMessage());
        }
    }

    /**
     * 上传Icon
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uplodeIcon(Request $request)
    {
        $this->validate($request, [
            // 'file' => 'required|file|max:200'
            'file' => 'required|file'
        ]);
        //返回信息json
        $file = $request->file('file');

        try{
            if (!$file->isValid()){
                throw new \Exception($file->getErrorMessage());
            }
            $main_id = Auth::user()->getMainId();
            $dir = "icon/{$main_id}";
            $file_name = date('Ymd').time().uniqid().".".$file->getClientOriginalExtension();
            $path = Storage::putFileAs($dir, $file, $file_name);

            if($path){
                $data = [
                    'code'  => 0,
                    'msg'   => '上传成功',
                    'url' => Storage::url($path),
                ];
            }else{
                $data['msg'] = $file->getErrorMessage();
            }
            return response()->json($data);
        }catch (\Exception $ex){
            $data = [
                'code'=>1,
                'msg'=>$ex->getMessage()
            ];
            return response()->json($data);
        }
    }

    public function uplodeApk(Request $request)
    {
        $this->validate($request, [
            // 'file' => 'required|file|max:200'
            'file' => 'required|file'
        ]);
        //返回信息json
        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        Log::info('extension--'. $ext);
        Log::info($file->getClientOriginalName());
        try{
            if (!$file->isValid()){
                throw new \Exception($file->getErrorMessage());
            }
            if ($ext != 'apk') {
                return $this->fail(1005, [], 'wrong format');
            }
            $main_id = Auth::user()->getMainId();
            $dir = "apk/{$main_id}/". date('Ymd'). '/'.Uuid::uuid4();
            // $file_name = $file->getClientOriginalName().".".$file->getClientOriginalExtension();
            $file_name = $file->getClientOriginalName();
            $path = Storage::putFileAs($dir, $file, $file_name);

            if($path){
                $data = [
                    'code'  => 0,
                    'msg'   => '上传成功',
                    'url' => Storage::url($path),
                ];
            }else{
                $data['msg'] = $file->getErrorMessage();
            }
            return response()->json($data);
        }catch (\Exception $ex){
            $data = [
                'code'=>1,
                'msg'=>$ex->getMessage()
            ];
            return response()->json($data);
        }
    }

    /**
     * 启动
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function enable($id)
    {
        /** @var App $app */
        $app = App::findOrFail($id);
        if ($app->is_admin_disable == 1 || $app->is_remove == 1){
            return $this->fail(100, [], 'Under review by administrator,please contact the administrator');
        }else {
            $app->enable();
            return $this->success();
        }
    }

    /**
     * 停止
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function disable($id)
    {
        /** @var App $app */
        $app = App::findOrFail($id);
        $app->disable();
        return $this->success();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $ids = $request->get('ids');
        if (empty($ids)){
            return response()->json(['code'=>1,'msg'=>'请选择删除项']);
        }
        if (App::destroy($ids)){
            return response()->json(['code'=>0,'msg'=>'删除成功']);
        }
        return response()->json(['code'=>1,'msg'=>'删除失败']);
    }
}
