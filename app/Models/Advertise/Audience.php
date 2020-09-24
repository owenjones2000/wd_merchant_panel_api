<?php

namespace App\Models\Advertise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Audience extends Model
{
    protected $table = 'a_campaign_audience';

    protected $fillable = ['gender', 'adult', 'campaign_id'];

}
