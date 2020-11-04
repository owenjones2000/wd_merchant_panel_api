<?php

namespace App\Models\Advertise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignBid extends Model
{
    use SoftDeletes;
    protected $table = 'a_campaign_bid';

    protected $fillable = ['type', 'amount', 'country', 'deleted_at'];

    /**
     * 指定国家
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function region(){
        return $this->belongsTo(Region::class, 'country', 'code');
    }
}
