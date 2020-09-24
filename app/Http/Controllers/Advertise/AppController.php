<?php

namespace App\Http\Controllers\Advertise;

use App\Exceptions\BizException;
use App\Models\Advertise\App;
use App\Rules\AdvertiseName;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Advertise\AppTag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
            ->orderBy('name','asc')
            ->paginate($request->get('limit',30))
            ->toArray();

        $data = [
            'code' => 0,
            'msg'   => '正在请求中...',
            'count' => $res['total'],
            'data'  => $res['data']
        ];
        return response()->json($data);
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

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function save(Request $request, $id = null)
    {
        $this->validate($request,[
            'name'  => [
                'required',
                'string',
                'unique:a_app,name,'.$id.',id,os,'.$request->input('os'),
                new AdvertiseName()
            ],
            'bundle_id'  => 'required|unique:a_app,bundle_id,'.$id.',id,os,'.$request->input('os'),
            'description' => 'string|max:200',
            'icon_url' => 'string|max:200',
            'track_url' => 'string|required',
            'app_id' => 'string|',
        ]);
        try{
            $params = $request->all();
            if($request->input('os') == 'ios'){
                if (strlen($request->input('app_id')) > 10 || strlen($request->input('app_id')) < 8){
                    return back()->withInput()->withErrors(['app_id is wrong']);
                }else{
                    $params['app_id'] = 'id'.$params['app_id'];
                }
                
            }
            $params['id'] = $id;
            App::Make(Auth::user(), $params);
            return redirect(route('advertise.app.edit', [$id]))->with(['status'=>'Update successfully']);
        } catch(BizException $ex){
            return redirect(route('advertise.app.edit', [$id]))->withErrors(['status'=>$ex->getMessage()]);
        }
    }

    /**
     * 上传Icon
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uplodeIcon(Request $request)
    {
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
            return response()->json(['code' => 100, 'msg' => 'Under review by administrator,please contact the administrator']);
        }else {
            $app->enable();
            return response()->json(['code' => 0, 'msg' => 'Successful']);
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
        return response()->json(['code'=>0,'msg'=>'Successful']);
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
