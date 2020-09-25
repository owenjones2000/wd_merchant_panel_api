<?php

namespace App\Models\Advertise;

use App\Scopes\TenantScope;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Campaign extends Model
{
    use SoftDeletes;

    protected $table = 'a_campaign';

    protected $fillable = ['name', 'app_id', 'main_user_id'];

    protected $appends = ['default_budget', 'default_bid'];

    /**
     * 构造Campaign
     * @param User $user
     * @param $params
     * @return mixed
     */
    public static function Make($user, $params)
    {
        $campaign = DB::transaction(function () use ($user, $params) {
            $main_user_id = $user->getMainId();
            if (empty($params['id'])) {
                $campaign = new self();
                $campaign->main_user_id = $main_user_id;
                $campaign['status'] = true;
            } else {
                $campaign = self::query()->where([
                    'id' => $params['id'],
                    'main_user_id' => $main_user_id
                ])->firstOrFail();
            }
            $campaign->fill($params);
            $campaign->saveOrFail();
            if (empty($campaign['audience'])) {
                $audience = new Audience([
                    'campaign_id' => $campaign['id']
                ]);
            } else {
                $audience = $campaign['audience'];
            }
            if ($campaign->app->is_audience ?? 0) {
                $audience['gender'] = $params['audience']['gender'] ?? 0;
                $audience['adult'] = $params['audience']['adult'] ?? false;
                $audience['states'] =  ',' . ($params['audience']['states'] ?? '') . ',';
            }
            $audience->saveOrFail();

            if (empty($params['regions'])) {
                $campaign->regions()->sync([]);
            } else {
                $region_code_list = is_array($params['regions']) ?
                    $params['regions'] : explode(',', $params['regions']);
                $regions = Region::query()
                    ->whereIn('code', $region_code_list)
                    ->pluck('code');
                $campaign->regions()->sync($regions);
            }

            if (/*isset($params['budget_by_region']) &&*/isset($params['budget'])) {
                $campaign->budgets()->updateOrCreate([
                    'country' => 'ALL',
                ], [
                    'amount' => $params['budget'][0]['amount'] ?? 0,
                ]);
                if ($params['budget_by_region'] ?? false) {
                    foreach ($params['budget'] as $budget_info) {
                        if (!empty($budget_info['region_code']) && !empty($budget_info['amount'])) {
                            $campaign->budgets()->updateOrCreate([
                                'country' => $budget_info['region_code'],
                            ], [
                                'amount' => $budget_info['amount'],
                                'deleted_at' => null,
                            ]);
                        }
                    }
                } else {
                    $campaign->budgets()->where('country', '!=', 'ALL')
                        ->update([
                            'deleted_at' => Carbon::now(),
                        ]);
                }
            }

            if (isset($params['bid_by_region']) && isset($params['bid'])) {
                $campaign->bids()->updateOrCreate([
                    'country' => 'ALL',
                ], [
                    'amount' => $params['bid'][0]['amount'] ?? 1,
                ]);
                if ($params['bid_by_region']) {
                    foreach ($params['bid'] as $bid_info) {
                        if (!empty($bid_info['region_code'])) {
                            if (!empty($bid_info['amount']) && $bid_info['amount'] > 0) {
                                $campaign->bids()->updateOrCreate([
                                    'country' => $bid_info['region_code'],
                                ], [
                                    'amount' => $bid_info['amount'],
                                    'deleted_at' => null,
                                ]);
                            } else {
                                $campaign->bids()->where([
                                    'country' => $bid_info['region_code'],
                                ])->update([
                                    'deleted_at' => Carbon::now(),
                                ]);
                            }
                        }
                    }
                } else {
                    $campaign->bids()->where('country', '!=', 'ALL')
                        ->update([
                            'deleted_at' => Carbon::now(),
                        ]);
                }
            }

            return $campaign;
        }, 3);
        return $campaign;
    }

    /**
     * 启用
     * @throws \Throwable
     */
    public function enable()
    {
        if ($this->is_admin_disable) {
            throw new \Exception('This campaign has been disabled by the administrator.');
        }
        if (!$this->status) {
            $this->status = true;
            $this->saveOrFail();
        }
    }

    /**
     * 停用
     * @throws \Throwable
     */
    public function disable()
    {
        if ($this->status) {
            $this->status = false;
            $this->saveOrFail();
        }
    }

    /**
     * 构造Ad
     * @param User $user
     * @param $params
     * @return Ad
     */
    public function makeAd($user, $params)
    {
        $ad = DB::transaction(function () use ($user, $params) {
            if (empty($params['id'])) {
                $ad = new Ad();
                $ad['campaign_id'] = $this['id'];
                $ad['app_id'] = $this['app_id'];
                $ad['status'] = false;
            } else {
                $ad = $this->ads()->where([
                    'id' => $params['id'],
                ])->firstOrFail();
            }
            $ad->fill($params);
            $ad->saveOrFail();
            if (empty($params['id'])) {
                $ad->regions()->syncWithoutDetaching(['ALL']);
            }

            if (isset($params['asset'])) {
                $asset_id_list = array_column($params['asset'], 'type', 'id');
                $ad->assets()
                    ->where(function ($query) use ($ad, $asset_id_list) {
                        $query->whereNotIn('id', array_keys($asset_id_list))
                            ->orWhereNotIn('type_id', $ad['type']['support_asset_type']);
                    })
                    ->update([
                        'ad_id' => null
                    ]);
                $append_asset_query = Asset::query()
                    ->where(function ($query) use ($ad, $asset_id_list) {
                        $query->whereIn('id', array_keys($asset_id_list))
                            ->whereIn('type_id', $ad['type']['support_asset_type']);
                    })
                    ->whereNull('ad_id');
                $html_asset_query = clone $append_asset_query;
                $html_asset = $html_asset_query->where('type_id', AssetType::Html)->first();
                // dd($html_asset, $asset_id_list);
                if ($html_asset) {
                    $ad['need_review'] = true;
                    $ad['status'] = false;
                    $ad->saveOrFail();
                    if ($params['playable'] == 1){
                        $html_asset->type_id = AssetType::Playable_Html;
                        $html_asset->save();
                    }else {
                        $html_asset->type_id = AssetType::Html;
                        $html_asset->save();
                    }
                }
                $append_asset_query->update([
                    'ad_id' => $ad['id']
                ]);
            }

            $tags = [];
            if ($params['tags']??null) {
                $tags = explode(',', $params['tags']);
                $ad->tags()->sync($tags);
            }
            
            return $ad;
        }, 3);
        return $ad;
    }

    /**
     * 默认预算
     * @return int
     */
    public function getDefaultBudgetAttribute()
    {
        $default_budget = $this->budgets->where('country', 'ALL')->first();
        unset($this->budgets);
        if (empty($default_budget)) {
            return 0;
        } else {
            return $default_budget['amount'];
        }
    }

    /**
     * 默认出价
     * @return int
     */
    public function getDefaultBidAttribute()
    {
        $default_bid = $this->bids->where('country', 'ALL')->first();
        unset($this->bids);
        if (empty($default_bid)) {
            return 0;
        } else {
            return $default_bid['amount'];
        }
    }

    /**
     * 所属应用
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function app()
    {
        return $this->belongsTo(App::class, 'app_id', 'id');
    }

    /**
     * 广告
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ads()
    {
        return $this->hasMany(Ad::class, 'campaign_id', 'id');
    }

    /**
     * 投放国家
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function regions()
    {
        return $this->belongsToMany(
            Region::class,
            'a_campaign_country',
            'campaign_id',
            'country',
            'id',
            'code'
        );
    }

    /**
     * 受众
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function audience()
    {
        return $this->hasOne(Audience::class, 'campaign_id', 'id');
    }

    /**
     * 三方跟踪
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function track()
    {
        return $this->hasOne(Track::class, 'campaign_id', 'id');
    }

    /**
     * 预算
     */
    public function budgets()
    {
        return $this->hasMany(CampaignBudget::class, 'campaign_id', 'id');
    }

    /**
     * 出价
     *
     */
    public function bids()
    {
        return $this->hasMany(CampaignBid::class, 'campaign_id', 'id');
    }

    /**
     * 禁用投放渠道
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function disableChannels()
    {
        return $this->belongsToMany(
            Channel::class,
            'a_campaign_target_app_disabled',
            'campaign_id',
            'target_app_id',
            'id',
            'id'
        );
    }

    /**
     * 投放渠道白名单
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function whiteListChannels()
    {
        return $this->belongsToMany(
            Channel::class,
            'a_campaign_target_app_whitelist',
            'campaign_id',
            'target_app_id',
            'id',
            'id'
        );
    }

    /**
     *  模型的 「启动」 方法.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new TenantScope(Auth::user()->getMainId()));
    }
}
