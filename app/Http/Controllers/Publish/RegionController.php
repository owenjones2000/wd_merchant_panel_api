<?php

namespace App\Http\Controllers\Publish;

use App\Models\Advertise\AdvertiseKpi;
use App\Models\Advertise\App;
use App\Models\Advertise\Ad;
use App\Models\Advertise\Campaign;
use App\Models\Advertise\Channel;
use App\Models\Advertise\Region;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function list(Request $request, $campaign_id)
    {
        $rangedate = $request->input('rangedate', date('Y-m-d ~ Y-m-d'));
        $campaign = Campaign::findOrFail($campaign_id);
        return view('advertise.campaign.region.list', compact('campaign', 'rangedate'));
    }

    public function data(Request $request, $campaign_id)
    {
        if(!empty($request->get('rangedate'))){
            $range_date = explode(' ~ ',$request->get('rangedate'));
        }
        $start_date = date('Ymd', strtotime($range_date[0]??'now'));
        $end_date = date('Ymd', strtotime($range_date[1]??'now'));
        $order_by = explode('.', $request->get('field', 'status'));
        $order_sort = $request->get('order', 'desc') ?: 'desc';

        $region_id_query = Region::query();
        if(!empty($request->get('keyword'))){
            $like_keyword = '%'.$request->get('keyword').'%';
            $region_id_query->where('name', 'like', $like_keyword);
        }

        $region_id_query->select('code');
        $advertise_kpi_query = AdvertiseKpi::multiTableQuery(function($query) use($start_date, $end_date, $region_id_query, $campaign_id){
            $query->whereBetween('date', [$start_date, $end_date])
                ->where('campaign_id', $campaign_id)
                ->whereIn('country', $region_id_query);
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
            'country',
        ]);
        $advertise_kpi_query->groupBy('country');
        if($order_by[0] === 'kpi' && isset($order_by[1])){
            $advertise_kpi_query->orderBy($order_by[1], $order_sort);
        }
        
        $advertise_kpi_list = $advertise_kpi_query
            ->with('region')
            ->orderBy('spend','desc')
            ->paginate($request->get('limit',30))
            ->toArray();

        $data = [
            'code' => 0,
            'msg'   => '正在请求中...',
            'count' => $advertise_kpi_list['total'],
            'data'  => $advertise_kpi_list['data']
        ];
        return response()->json($data);
    }
}
