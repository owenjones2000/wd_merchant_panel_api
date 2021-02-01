<?php

namespace App\Http\Controllers\Advertise;

use App\Http\Controllers\Controller;
use App\Jobs\CompressVideo;
use App\Models\Advertise\AdType;
use App\Models\Advertise\Asset;
use App\Models\Advertise\AssetType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{

    public function processMediaFiles(Request $request)
    {
        //返回信息json
        $file = $request->file('file');

        try {
            if (!$file->isValid()) {
                throw new \Exception($file->getErrorMessage());
            }
            $file_info = AssetType::decide($file);
            $ad_type = AdType::get($request->input('ad_type_id', null));
            if ($ad_type != null) {
                if (!in_array($file_info['type'], $ad_type['support_asset_type'])) {
                    throw new \Exception('file type not support by ad.');
                }
            }
            $main_id = Auth::user()->getMainId();
            $dir = "asset/{$main_id}";
            $file_name = date('Ymd') . time() . uniqid() . "." . $file->getClientOriginalExtension();
            $path = Storage::putFileAs($dir, $file, $file_name);
            Storage::disk('local')->putFileAs($dir, $file, $file_name);

            $asset = Asset::create([
                'url' => Storage::url($path),
                'file_path' => $path,
                'hash' => md5_file($file),
                'type_id' => $file_info['type'],
                'spec' => $file_info,
                'width' => $file_info['width'] ?? 0,
                'height' => $file_info['height'] ?? 0,
                'duration' => $file_info['duration'] ?? 0,
            ]);
            // dispatch(new CompressVideo($asset));
            $asset['type_group_key'] = AdType::getAssetTypeGroupKey($ad_type['id'], $file_info['type']);
            $asset['type'] = AssetType::get($asset['type_id']);

            if ($path) {
                $data = [
                    'code'  => 0,
                    'msg'   => '上传成功',
                    'asset' => $asset,
                ];
            } else {
                $data['msg'] = $file->getErrorMessage();
            }
            return response()->json($data);
        } catch (\Exception $ex) {
            $data = [
                'code' => 1,
                'msg' => $ex->getMessage()
            ];
            return response()->json($data);
        }
    }
}
