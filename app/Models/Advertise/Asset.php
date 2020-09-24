<?php
namespace App\Models\Advertise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;

    protected $table = 'a_asset';

    protected $fillable = ['url', 'file_path', 'hash', 'type_id', 'width', 'height', 'duration', 'spec', 'ad_id'];

    protected $appends = ['type'];

    protected $casts = [
        'spec' => 'json'
    ];

    /**
     * Asset类型
     * @return mixed
     */
    public function getTypeAttribute(){
        return AssetType::get($this->type_id);
    }
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ad(){
        return $this->belongsTo(Ad::class, 'ad_id', 'id');
    }
}
