<?php
namespace App\Models\Advertise;

use FFMpeg\FFProbe;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AssetType
{
    const Landscape_Short = 1;
    const Landscape_Long = 2;
    const Portrait_Short = 3;
    const Portrait_Long = 4;
    const Landscape_Interstitial_Image = 5;
    const Portrait_Interstitial_Image = 6;
    const Html = 7;
    const Playable_Html = 8;

    public static function get($asset_type_id){
        return self::$list[$asset_type_id];
    }

    /**
     * @param UploadedFile $file
     * @return array
     */
    public static function decide($file){
        $whole_mime_type = $file->getMimeType();
        $mime_type = substr($whole_mime_type, 0, strpos($whole_mime_type, '/'));
        switch($mime_type) {
            case 'video':
                $ffprobe = FFProbe::create([
                    'ffmpeg.binaries' => env('FFMPEG_BIN_PATH', '/usr/local/bin/ffmpeg'),
                    'ffprobe.binaries' => env('FFPROBE_BIN_PATH', '/usr/local/bin/ffprobe')
                ]);
                $video_info = $ffprobe->streams($file)->videos()->first()->all();
                $width = Arr::get($video_info, 'width');
                $height = Arr::get($video_info, 'height');
                $duration = Arr::get($video_info, 'duration');
                // $bit_rate = Arr::get($video_info, 'bit_rate');
                if ($width >= $height) {
                    $type = $duration > 15 ?
                        AssetType::Landscape_Long : AssetType::Landscape_Short;
                } else {
                    $type = $duration > 15 ?
                        AssetType::Portrait_Long : AssetType::Portrait_Short;
                }
                $file_info = [
                    'type' => $type,
                    'width' => $width,
                    'height' => $height,
                    // 'bit_rate' => $bit_rate,
                    'duration' => $duration
                ];
                break;
            case 'image':
                $image_info = getimagesize($file);
                $width = $image_info[0]??0;
                $height = $image_info[1]??0;
                if ($width >= $height) {
                    $type = AssetType::Landscape_Interstitial_Image;
                } else {
                    $type = AssetType::Portrait_Interstitial_Image;
                }
                $file_info = [
                    'type' => $type,
                    'width' => $width,
                    'height' => $height
                ];
                break;
            case 'text':
                if (in_array($file->getClientOriginalExtension(), ['html', 'htm'])) {
                    $file_info = [
                        'type' => AssetType::Html,
                    ];
                }
                break;
            default:
                throw new \Exception('file type not support.');
        }

        return $file_info;
    }

    public static $list = [
        self::Landscape_Short => [
            'id' => self::Landscape_Short,
            'name' => 'Landscape Short Video',
            'mime_type' => 'video'
        ],
        self::Landscape_Long => [
            'id' => self::Landscape_Long,
            'name' => 'Landscape Long Video',
            'mime_type' => 'video'
        ],
        self::Portrait_Short => [
            'id' => self::Portrait_Short,
            'name' => 'Portrait Short Video',
            'mime_type' => 'video'
        ],
        self::Portrait_Long => [
            'id' => self::Portrait_Long,
            'name' => 'Portrait Long Video',
            'mime_type' => 'video'
        ],
        self::Landscape_Interstitial_Image => [
            'id' => self::Landscape_Interstitial_Image,
            'name' => 'Landscape Interstitial Image',
            'mime_type' => 'image'
        ],
        self::Portrait_Interstitial_Image => [
            'id' => self::Portrait_Interstitial_Image,
            'name' => 'Portrait Interstitial Image',
            'mime_type' => 'image'
        ],
        self::Html => [
            'id' => self::Html,
            'name' => 'HTML File',
            'mime_type' => 'html'
        ],
        self::Playable_Html => [
            'id' => self::Playable_Html,
            'name' => 'Playable Html File',
            'mime_type' => 'html'
        ],
    ];
}
