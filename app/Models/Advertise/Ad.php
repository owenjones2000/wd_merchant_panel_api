<?php
namespace App\Models\Advertise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ad extends Model
{
    use SoftDeletes;

    protected $table = 'a_ad';

    protected $fillable = ['name', 'type_id', 'campaign_id'];

    protected $appends = ['type', 'is_upload_completed'];

    /**
     * 启用
     * @throws \Throwable
     */
    public function enable(){
        if($this->is_admin_disable){
            throw new \Exception('This ad has been disabled by the administrator.');
        }
        if(!$this->status){
            if(!$this->is_upload_completed){
                throw new \Exception('Lack of assets.');
            }
            if($this->need_review){
                throw new \Exception('Need review by administrator.');
            }
            // $this->is_cold = true;
            $this->status = true;
            $this->saveOrFail();
        }
    }

    /**
     * 停用
     * @throws \Throwable
     */
    public function disable(){
        if($this->status){
            $this->status = false;
            $this->saveOrFail();
        }
    }

    public function cloneAd($campaign_id = null)
    {
        $newAd = $this->replicate();
        
        $newAd->status = false;
        $newAd->is_cold = 1;
        $newAd->name = $newAd->name.'_copy';
        if($campaign_id){
            $campaign = Campaign::findOrFail($campaign_id);
            $newAd->campaign_id = $campaign_id;
            $newAd->app_id = $campaign->app_id;
        }
        $newAd->save();
        $newAd->regions()->syncWithoutDetaching(['ALL']);
        foreach ($this->assets as  $asset) {
            $newAsset = $asset->replicate();
            $newAsset ->ad_id = $newAd->id;
            $newAsset->save();
        }
        
    }
    /**
     * 广告活动
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function campaign(){
        return $this->belongsTo(Campaign::class, 'campaign_id', 'id');
    }

    /**
     * 投放国家
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function regions(){
        return $this->belongsToMany(Region::class, 'a_ad_country',
            'ad_id','country', 'id', 'code');
    }

    /**
     * 广告类型
     * @return AdType
     */
    public function getTypeAttribute(){
        return AdType::get($this->type_id);
    }

    /**
     * 素材是否满足
     * @return bool
     */
    public function getIsUploadCompletedAttribute(){
        if (isset($this['type']['need_asset_type']) && is_array($this['type']['need_asset_type'])) {
            foreach ($this['type']['need_asset_type'] as $need_asset_type) {
                if (is_array($need_asset_type)) {
                    foreach ($need_asset_type as $need_asset_type_item) {
                        if($this['assets']->contains('type_id', $need_asset_type_item)){
                            continue 2;
                        }
                    }
                } else {
                    if($this['assets']->contains('type_id', $need_asset_type)){
                        continue ;
                    }
                }
                return false;
            }
        }
        return true;
    }

    /**
     * 素材
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function assets(){
        return $this->hasMany(Asset::class, 'ad_id', 'id');
    }

    public function tags()
    {
        return $this->belongsToMany(AdTag::class, 'a_ad_tags', 'ad_id', 'tag_id', 'id', 'id');
    }
}
