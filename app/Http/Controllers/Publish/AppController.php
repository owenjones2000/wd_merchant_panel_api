<?php

namespace App\Http\Controllers\Publish;

use App\Exceptions\BizException;
use App\Models\Advertise\AdvertiseKpi;
use App\Models\Advertise\App;
use App\Models\Advertise\Channel;
use App\Rules\AdvertiseName;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Advertise\Impression;
use App\Models\Advertise\Region;
use App\Models\ChannelCpm;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AppController extends Controller
{

    public function listdata(Request $request)
    {
        $channel_base_query = Channel::query()->where('main_user_id', Auth::user()->getMainId());
        if (!empty($request->get('keyword'))) {
            $like_keyword = '%' . $request->get('keyword') . '%';
            $channel_base_query->where('name', 'like', $like_keyword);
        }
        if (!empty($request->get('platform'))) {
            $platform  = $request->get('platform');
            $channel_base_query->where('platform', $platform);
        }
        $channel_list = $channel_base_query->paginate($request->get('limit', 30));
        return $this->success($channel_list);
    }
    public function data(Request $request)
    {
        if (!empty($request->get('rangedate'))) {
            $range_date = explode(' ~ ', $request->get('rangedate'));
        }
        $start_date = date('Ymd', strtotime($range_date[0] ?? 'now'));
        $end_date = date('Ymd', strtotime($range_date[1] ?? 'now'));
        $order_by = explode('.', $request->get('field', 'status'));
        $order_sort = $request->get('order', 'desc') ?: 'desc';

        $channel_base_query = Channel::query()->where('main_user_id', Auth::user()->getMainId());
        if (!empty($request->get('keyword'))) {
            $like_keyword = '%' . $request->get('keyword') . '%';
            $channel_base_query->where('name', 'like', $like_keyword);
        }
        if (!empty($request->get('platform'))) {
            $platform  = $request->get('platform');
            $channel_base_query->where('platform', $platform);
        }
        $country = $request->get('country');
        $type = $request->get('type');
        $channel_id_query = clone $channel_base_query;
        $channel_id_query->select('id');
        // dd($channel_id_query->get());
        $advertise_kpi_query = AdvertiseKpi::multiTableQuery(function ($query) use (
            $start_date,
            $end_date,
            $channel_id_query,
            $country,
            $type
        ) {
            $query->whereBetween('date', [$start_date, $end_date])
                ->whereIn('target_app_id', $channel_id_query)
                ->select([
                    'impressions',
                    'clicks',
                    // 'installations', 
                    'spend',
                    'date',
                    'target_app_id',
                    'country',
                    'type',
                ]);
            if ($country) {
                $query->where('country', $country);
            }
            if ($type) {
                $query->where('type', $type);
            }
            return $query;
        }, $start_date, $end_date);

        $advertise_kpi_query->select([
            DB::raw('sum(impressions) as impressions'),
            DB::raw('sum(clicks) as clicks'),
            // DB::raw('sum(installations) as installs'),
            DB::raw('round(sum(clicks) * 100 / sum(impressions), 2) as ctr'),
            // DB::raw('round(sum(installations) * 100 / sum(clicks), 2) as cvr'),
            // DB::raw('round(sum(installations) * 100 / sum(impressions), 2) as ir'),
            DB::raw('round(sum(spend), 2) as spend'),
            // DB::raw('round(sum(spend) / sum(installations), 2) as ecpi'),
            DB::raw('round(sum(spend) * 1000 / sum(impressions), 2) as ecpm'),
            'target_app_id',
        ]);

        // if ($country) {
        //     $advertise_kpi_query->addSelect('country');
        //     $advertise_kpi_query->groupBy('target_app_id', 'country');
        // } else {
        //     $advertise_kpi_query->groupBy('target_app_id');
        // }
        $advertise_kpi_query->groupBy('target_app_id');


        $advertise_kpi_list = $advertise_kpi_query
            ->orderBy('spend', 'desc')
            ->paginate($request->get('limit', 30))
            ->toArray();
        $channel_query = clone $channel_base_query;

        $channel_list = $channel_query->get()->keyBy('id')
            ->toArray();
        //spend 从impression表取

        // if ($country) {
        //     $impression_list = ChannelCpm::whereBetween('date', [$start_date, $end_date])
        //         ->join('a_target_apps', 'a_target_app_cpm.target_app_id', '=', 'a_target_apps.id')
        //         ->whereIn('target_app_id', $channel_id_query)
        //         ->when($type, function ($query) use ($type) {
        //             $query->where('type', $type);
        //         })
        //         ->select([
        //             DB::raw('sum(cpm_revenue * (1-rate/100)) as cpm'),
        //             'target_app_id',
        //             'country',
        //         ])->groupBy('target_app_id', 'country')
        //         ->get()
        //         ->toArray();
        // } else {
            $impression_list = ChannelCpm::whereBetween('date', [$start_date, $end_date])
                ->join('a_target_apps', 'a_target_app_cpm.target_app_id', '=', 'a_target_apps.id')
                ->whereIn('target_app_id', $channel_id_query)
                ->when($type, function ($query) use ($type) {
                    $query->where('type', $type);
                })
                ->when($country, function ($query) use ($country) {
                    $query->where('country', $country);
                })
                ->select([
                    DB::raw('sum(cpm_revenue * (1-rate/100)) as cpm'),
                    'target_app_id',
                    'country',
                ])->groupBy('target_app_id')
                ->get()
                ->keyBy('target_app_id')
                ->toArray();;
        // }

        foreach ($advertise_kpi_list['data'] as $key => &$kpi) {
            $kpi['ecpm'] = 0;
            $kpi['spend'] = round($impression_list[$kpi['target_app_id']]['cpm'] ?? 0, 2);
            if ($kpi['spend']){
                $kpi['ecpm'] = round($kpi['spend'] * 1000 / $kpi['impressions'] ?? 0, 2);
            }
            $kpi['app'] = $channel_list[$kpi['target_app_id']] ?? null;
            $kpi['country'] = $country ?? null;
            $kpi['type'] = $type ?? null;
            // if ($country) {
            //     foreach ($impression_list as $key => $cpm) {
            //         if ($cpm['target_app_id'] == $kpi['target_app_id'] && $cpm['country'] == $kpi['country']) {
            //             $kpi['spend'] = round($cpm['cpm'] ?? 0, 2);
            //             $kpi['ecpm'] = round($kpi['spend'] * 1000 / $kpi['impressions'] ?? 0, 2);
            //         }
            //     }
            // } else {
            //     $kpi['spend'] = round($impression_list[$kpi['target_app_id']]['cpm'] ?? 0, 2);
            //     $kpi['ecpm'] = round($kpi['spend'] * 1000 / $kpi['impressions'] ?? 0, 2);
            // }
        }

        return $this->success($advertise_kpi_list);
    }
    public function dashboard()
    {
        return view('publish.app.dashboard');
    }

    public function dashboardData(Request $request)
    {
        $range_date = $request->get('range_date', null);
        if ($range_date != 'now' && $range_date != null) {
            $range_date = explode(' ~ ', $request->get('range_date'));
        }
        $start_date = date('Ymd', strtotime($range_date[0] ?? '-7 day'));
        $end_date = date('Ymd', strtotime($range_date[1] ?? '-1 day'));
        // dd(Auth::user()->getMainId());
        $channel_base_query = Channel::query()->where('main_user_id', Auth::user()->getMainId());

        $channel_id_query = clone $channel_base_query;
        $channel_id_query->select('id');
        $advertise_kpi_query = AdvertiseKpi::multiTableQuery(function ($query) use (
            $start_date,
            $end_date,
            $channel_id_query
        ) {
            $query->whereBetween('date', [$start_date, $end_date])
                ->whereIn('target_app_id', $channel_id_query)
                ->select([
                    'impressions', 'clicks', 'installations', 'spend',
                    'date',
                    'target_app_id',
                ]);
            return $query;
        }, $start_date, $end_date);

        $advertise_kpi_query
            ->select([
                DB::raw('sum(impressions) as impressions'),
                DB::raw('sum(clicks) as clicks'),
                // DB::raw('sum(installations) as installs'),
                DB::raw('round(sum(clicks) * 100 / sum(impressions), 2) as ctr'),
                // DB::raw('round(sum(installations) * 100 / sum(clicks), 2) as cvr'),
                // DB::raw('round(sum(installations) * 100 / sum(impressions), 2) as ir'),
                DB::raw('round(sum(spend), 2) as spend'),
                // DB::raw('round(sum(spend) / sum(installations), 2) as ecpi'),
                DB::raw('round(sum(spend) * 1000 / sum(impressions), 2) as ecpm'),
                'date',
            ]);
        $advertise_kpi_query->groupBy('date');
        $advertise_kpi_list = $advertise_kpi_query
            ->orderBy('date', 'asc')
            ->get()
            ->toArray();
        $impression_cpm = ChannelCpm::whereBetween('date', [$start_date, $end_date])
            ->join('a_target_apps', 'a_target_app_cpm.target_app_id', '=', 'a_target_apps.id')
            ->whereIn('target_app_id', $channel_id_query)
            ->select([
                DB::raw('sum(cpm_revenue * (1-rate/100)) as cpm'),
                'date',
            ])->groupBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();
        foreach ($advertise_kpi_list as $key => &$kpi) {
            $kpi['revenue'] = round($impression_cpm[$kpi['date']]['cpm'] ?? 0);
            $kpi['ecpm'] = round($kpi['revenue'] * 1000 / $kpi['impressions'] ?? 0, 2);
        }
        if ($range_date == 'now') {
            $result = $advertise_kpi_list[count($advertise_kpi_list) - 1] ?? [];
        } else {
            $result = $advertise_kpi_list;
        }

        return $this->success($result);
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
        if (empty($id)) {
            $apps = new Channel();
        } else {
            /** @var App $apps */
            $apps = Channel::findOrFail($id);
        }
        return view('publish.app.edit', compact('apps'));
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
        $this->validate($request, [
            'name'  => ['required', 'string', 'unique:a_target_apps,name,' . $id, new AdvertiseName()],
            'bundle_id'  => 'required|unique:a_target_apps,bundle_id,' . $id,
            'icon_url' => 'string|max:200',
        ]);
        try {
            $params = $request->all();
            $params['id'] = $id;
            Channel::Make(Auth::user(), $params);
            return redirect(route('publish.app.edit', [$id]))->with(['status' => 'Update successfully']);
        } catch (BizException $ex) {
            return redirect(route('publish.app.edit', [$id]))->withErrors(['status' => $ex->getMessage()]);
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

        try {
            if (!$file->isValid()) {
                throw new \Exception($file->getErrorMessage());
            }
            $main_id = Auth::user()->getMainId();
            $dir = "icon/{$main_id}";
            $file_name = date('Ymd') . time() . uniqid() . "." . $file->getClientOriginalExtension();
            $path = Storage::putFileAs($dir, $file, $file_name);

            if ($path) {
                $data = [
                    'code'  => 0,
                    'msg'   => '上传成功',
                    'url' => Storage::url($path),
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

    /**
     * 启动
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function enable($id)
    {
        /** @var Channel $apps */
        $apps = Channel::findOrFail($id);
        $apps->enable();
        return response()->json(['code' => 0, 'msg' => 'Successful']);
    }

    /**
     * 停止
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function disable($id)
    {
        /** @var Channel $apps */
        $apps = Channel::findOrFail($id);
        $apps->disable();
        return response()->json(['code' => 0, 'msg' => 'Successful']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    //    public function destroy(Request $request)
    //    {
    //        $ids = $request->get('ids');
    //        if (empty($ids)){
    //            return response()->json(['code'=>1,'msg'=>'请选择删除项']);
    //        }
    //        if (Channel::destroy($ids)){
    //            return response()->json(['code'=>0,'msg'=>'删除成功']);
    //        }
    //        return response()->json(['code'=>1,'msg'=>'删除失败']);
    //    }
}
