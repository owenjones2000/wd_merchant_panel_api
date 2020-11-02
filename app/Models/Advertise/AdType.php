<?php

namespace App\Models\Advertise;

class AdType
{
    const Video_Landscape_Short = 1;
    const Video_Landscape_Long = 2;
    const Video_Portrait_Short = 3;
    const Video_Portrait_Long = 4;

    public static function get($type_id)
    {
        if (isset(self::$list[$type_id])) {
            return self::$list[$type_id];
        }
        return null;
    }

    public static function getAssetTypeGroupKey($type_id, $asset_type_id)
    {
        $ad_type = self::get($type_id);
        if (!$ad_type) {
            return null;
        }
        foreach ($ad_type['need_asset_type'] as $group_key => $need_asset_type_item) {
            if ((is_array($need_asset_type_item) && in_array($asset_type_id, $need_asset_type_item))
                || $asset_type_id == $need_asset_type_item
            ) {
                return $group_key;
            }
        }
        return null;
    }

    public static $list = [
        self::Video_Landscape_Short => [
            'id' => self::Video_Landscape_Short,
            'name' => 'Landscape - Under 15s',
            'support_asset_type' => [
                AssetType::Landscape_Short,
                AssetType::Landscape_Interstitial_Image,
                AssetType::Html,
                AssetType::Playable_Html,
            ],
            'need_asset_type' => [
                AssetType::Landscape_Short, [
                    AssetType::Landscape_Interstitial_Image,
                    AssetType::Playable_Html,
                    AssetType::Html
                ]
            ],
        ],
        self::Video_Landscape_Long => [
            'id' => self::Video_Landscape_Long,
            'name' => 'Landscape - Over 15s',
            'support_asset_type' => [
                AssetType::Landscape_Long,
                AssetType::Landscape_Interstitial_Image,
                AssetType::Playable_Html,
                AssetType::Html
            ],
            'need_asset_type' => [
                AssetType::Landscape_Long, [
                    AssetType::Landscape_Interstitial_Image,
                    AssetType::Playable_Html,
                    AssetType::Html
                ]
            ],
        ],
        self::Video_Portrait_Short => [
            'id' => self::Video_Portrait_Short,
            'name' => 'Portrait - Under 15s',
            'support_asset_type' => [
                AssetType::Portrait_Short,
                AssetType::Portrait_Interstitial_Image,
                AssetType::Playable_Html,
                AssetType::Html
            ],
            'need_asset_type' => [
                AssetType::Portrait_Short, [
                    AssetType::Portrait_Interstitial_Image,
                    AssetType::Playable_Html,
                    AssetType::Html
                ]
            ],
        ],
        self::Video_Portrait_Long => [
            'id' => self::Video_Portrait_Long,
            'name' => 'Portrait - Over 15s',
            'support_asset_type' => [
                AssetType::Portrait_Long,
                AssetType::Portrait_Interstitial_Image,
                AssetType::Playable_Html,
                AssetType::Html
            ],
            'need_asset_type' => [
                AssetType::Portrait_Long, [
                    AssetType::Portrait_Interstitial_Image,
                    AssetType::Playable_Html,
                    AssetType::Html
                ]
            ],
        ],
    ];
}
