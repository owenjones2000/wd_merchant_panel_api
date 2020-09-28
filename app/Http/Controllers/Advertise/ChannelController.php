<?php

namespace App\Http\Controllers\Advertise;

use App\Models\Advertise\AdvertiseKpi;
use App\Models\Advertise\App;
use App\Models\Advertise\Ad;
use App\Models\Advertise\Campaign;
use App\Models\Advertise\Channel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChannelController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function list(Request $request, $campaign_id)
    {
        $rangedate = $request->input('rangedate', date('Y-m-d ~ Y-m-d'));
        $country = $request->input('country', '');
        $campaign = Campaign::findOrFail($campaign_id);
        return view('advertise.campaign.channel.list', compact(
            'campaign',
            'rangedate',
            'country'
        ));
    }

    public function data(Request $request, $campaign_id)
    {
        if (!empty($request->get('rangedate'))) {
            $range_date = explode(' ~ ', $request->get('rangedate'));
        }
        $start_date = date('Ymd', strtotime($range_date[0] ?? 'now'));
        $end_date = date('Ymd', strtotime($range_date[1] ?? 'now'));
        $order_by = explode('.', $request->get('field', 'status'));
        $order_sort = $request->get('order', 'desc') ?: 'desc';

        $channel_base_query = Channel::query();
        if (!empty($request->get('keyword'))) {
            $like_keyword = '%' . $request->get('keyword') . '%';
            $channel_base_query->where('name', 'like', $like_keyword);
        }
        $country = $request->input('country');
        $channel_id_query = clone $channel_base_query;
        $channel_id_query->select('id');
        $advertise_kpi_query = AdvertiseKpi::multiTableQuery(function ($query) use (
            $start_date,
            $end_date,
            $channel_id_query,
            $country,
            $campaign_id
        ) {
            $query->whereBetween('date', [$start_date, $end_date])
                ->where('campaign_id', $campaign_id)
                ->when($country, function ($query) use ($country) {
                    $query->where('country', $country);
                })
                ->whereIn('target_app_id', $channel_id_query)
                ->select([
                    'impressions', 'clicks', 'installations', 'spend',
                    'date', 'app_id', 'campaign_id', 'target_app_id',
                ]);
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
            'campaign_id',
            'app_id',
            'target_app_id',
        ]);
        $advertise_kpi_query->groupBy('target_app_id');
        if ($order_by[0] === 'kpi' && isset($order_by[1])) {
            $advertise_kpi_query->orderBy($order_by[1], $order_sort);
        }

        $advertise_kpi_list = $advertise_kpi_query
            ->with('channel:id,name_hash')
            ->with(['campaign.disableChannels'])
            ->orderBy('spend', 'desc')
            ->paginate($request->get('limit', 30));
        foreach ($advertise_kpi_list as $advertise_kpi) {
            $advertise_kpi['status'] = !$advertise_kpi['campaign']['disableChannels']->contains($advertise_kpi['target_app_id']);
        }

        return $this->success($advertise_kpi_list);
    }

    /**
     * 启动
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function enable($campaign_id, $channel_id)
    {
        // /** @var App $apps */
        // $apps = App::findOrFail($app_id);
        /** @var Campaign $campaign */
        $campaign  = Campaign::findOrFail($campaign_id);
        $campaign->disableChannels()->detach($channel_id);
        return $this->success();
    }

    /**
     * 停止
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function disable($campaign_id, $channel_id)
    {
        // /** @var App $apps */
        // $apps = App::findOrFail($app_id);
        /** @var Campaign $campaign */
        $campaign  = Campaign::findOrFail($campaign_id);
        $campaign->disableChannels()->attach($channel_id);
        return $this->success();
    }
}
