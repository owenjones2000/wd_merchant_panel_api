<?php

namespace App\Models\Advertise;

use Illuminate\Database\Eloquent\Model;

class CampaignBudget extends Model
{
    protected $table = 'a_campaign_daily_budget';

    protected $fillable = ['amount', 'country'];

    /**
     * 指定国家
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function region(){
        return $this->belongsTo(Region::class, 'country', 'code');
    }
}
