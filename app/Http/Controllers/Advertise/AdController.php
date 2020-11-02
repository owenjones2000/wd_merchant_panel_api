<?php

namespace App\Http\Controllers\Advertise;

use App\Models\Advertise\AdvertiseKpi;
use App\Models\Advertise\App;
use App\Models\Advertise\Ad;
use App\Models\Advertise\Campaign;
use App\Rules\AdvertiseName;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Advertise\AdTag;
use App\Models\Advertise\AdType;
use App\Models\Advertise\AssetType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdController extends Controller
{

    public function data(Request $request, $campaign_id)
    {
        if(!empty($request->get('rangedate'))){
            $range_date = explode(' ~ ',$request->get('rangedate'));
        }
        $start_date = date('Ymd', strtotime($range_date[0]??'now'));
        $end_date = date('Ymd', strtotime($range_date[1]??'now'));
        $order_by = explode('.', $request->get('field'));
        $order_sort = $request->get('order', 'desc') ?: 'desc';

        $ad_base_query = Ad::query()->where('campaign_id', $campaign_id);
        if(!empty($request->get('keyword'))){
            $like_keyword = '%'.$request->get('keyword').'%';
            $ad_base_query->where('name', 'like', $like_keyword);
        }
        $ad_id_query = clone $ad_base_query;
        $ad_id_query->select('id');
        $advertise_kpi_query = AdvertiseKpi::multiTableQuery(function($query) use($start_date, $end_date, $ad_id_query){
            $query->whereBetween('date', [$start_date, $end_date])
                ->whereIn('ad_id', $ad_id_query);
            return $query;
        }, $start_date, $end_date);

        $advertise_kpi_query->select([
            DB::raw('sum(impressions) as impressions'),
            DB::raw('sum(clicks) as clicks'),
            DB::raw('sum(installations) as installs'),
            DB::raw('round(sum(clicks) * 100 / sum(impressions), 2) as ctr'),
            DB::raw('round(sum(installations) * 100 / sum(clicks), 2) as cvr'),
            DB::raw('round(sum(installations) * 100 / sum(impressions), 2) as ir'),
            DB::raw('round(sum(spend), 2) as spend'),
            DB::raw('round(sum(spend) / sum(installations), 2) as ecpi'),
            DB::raw('round(sum(spend) * 1000 / sum(impressions), 2) as ecpm'),
            'ad_id',
        ]);
        $advertise_kpi_query->groupBy('ad_id');
        if($order_by[0] === 'kpi' && isset($order_by[1])){
            $advertise_kpi_query->orderBy($order_by[1], $order_sort);
        }

        $advertise_kpi_list = $advertise_kpi_query
            ->orderBy('spend','desc')
            ->get()
            ->keyBy('ad_id')
            ->toArray();
        $order_by_ids = implode(',', array_reverse(array_keys($advertise_kpi_list)));
        // $ad_base_query->with('campaign:id,name,app_id');
        if(!empty($order_by_ids)){
            $ad_base_query->orderByRaw(DB::raw("FIELD(id,{$order_by_ids}) desc"));
        }
        if($order_by[0] && $order_by[0] !== 'kpi'){
            $ad_base_query->orderBy($order_by[0], $order_sort);
        }
        $ad_list = $ad_base_query->with('assets')
            ->orderBy('id', 'desc')
            ->paginate($request->get('limit',30))
            ->toArray();

        foreach($ad_list['data'] as &$ad){
            if(isset($advertise_kpi_list[$ad['id']])){
                $ad['kpi'] = $advertise_kpi_list[$ad['id']];
            }
        }
        return $this->success($ad_list);
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
    public function edit($campaign_id, $id = null)
    {
        if($id == null){
            $ad = new Ad();
            $ad['campaign_id'] = $campaign_id;
        }else{
            $ad = Ad::query()->where(['id' => $id, 'campaign_id' => $campaign_id])->firstOrFail();
        }
        $tags = AdTag::where('status', 1)->select([
            'id',
            'name'
        ])
        ->get();
        $adtags = $ad->tags()->pluck('id');
        return view('advertise.campaign.ad.edit',compact('ad', 'tags', 'adtags'));
    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function save(Request $request, $campaign_id, $id = null)
    {
        $this->validate($request,[
            'name'  => [
                'required','string','max:100',
                'unique:a_ad,name,'.$id.',id,campaign_id,'.$campaign_id,
                new AdvertiseName()
            ],
            // 'app_id' => 'exists:a_app,id',
            // 'regions' => 'string',
            'asset' => 'array',
            'asset.*.id' => 'required|numeric',
            'asset.*.type' => 'required|numeric',
        ]);
        /** @var Campaign $campaign */
        $campaign = Campaign::query()->where([
            'id' => $campaign_id,
            'main_user_id' => Auth::user()->getMainId(),
        ])->firstOrFail();
        $params = $request->all();
        $params['id'] = $id;
//        $params['status'] = isset($params['status']) ? 1 : 0;
        $ad = $campaign->makeAd(Auth::user(), $params);
        if ($ad){
            return $this->success();
        }
        return $this->fail(1004);
    }

    public function tags()
    {
        $tags = AdTag::where('status', 1)->select([
            'id',
            'name'
        ])
            ->get();
        return $this->success($tags);
    }

    public function type()
    {
        $type = AdType::$list;
        foreach ($type as  &$adtype) {
            foreach ($adtype['need_asset_type'] as $key => $needtype) {
                if (is_array($needtype)) {
                    $name = [];
                    foreach ($needtype as $astype) {
                        $name[] = AssetType::get($astype)['name'];  
                    }
                    $adtype['need_asset_type'][$key] = implode(' or ', $name);
                }else {
                    $adtype['need_asset_type'][$key]  = AssetType::get($needtype)['name'];
                }
            }
        }
        return $this->success(array_values($type));
    }
    public function assettype()
    {
        $type = AssetType::$list;
        return $this->success($type);
    }
    /**
     * 启动
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function enable($campaign_id, $id)
    {
        try{
            /** @var Ad $ad */
            $ad = Ad::query()->where(['id' => $id, 'campaign_id' => $campaign_id])->firstOrFail();
            $ad->enable();
            return $this->success();
        } catch (\Exception $ex) {
            return $this->fail(1002, [], $ex->getMessage());
        }

    }

    /**
     * 停止
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function disable($campaign_id, $id)
    {
        /** @var Ad $ad */
        $ad = Ad::query()->where(['id' => $id, 'campaign_id' => $campaign_id])->firstOrFail();
        $ad->disable();
        return $this->success();
    }

    public function cloneAd(Request $request, $campaign_id, $id)
    {
        $this->validate($request, [
            'campaigns' => 'required|string'
        ]);
        $campaignids = $request->input('campaigns');
        $campaignids = explode(',', $campaignids);
        /** @var Ad $ad */
        $ad = Ad::query()->where(['id' => $id, 'campaign_id' => $campaign_id])->firstOrFail();
        foreach ($campaignids as $key => $campaignId) {
            $ad->cloneAd($campaignId); 
        }

        return $this->success();
    }

    public function editClone($campaign_id, $id)
    {
        $campaign = Campaign::where('id', $campaign_id)->firstOrFail();
        $campaigns = Campaign::where('app_id', $campaign->app_id)->get();

        return $this->success($campaigns);
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
        if (Ad::destroy($ids)){
            return response()->json(['code'=>0,'msg'=>'删除成功']);
        }
        return response()->json(['code'=>1,'msg'=>'删除失败']);
    }
}
