<?php

namespace App\Console\Commands;

use App\Models\Advertise\Asset;
use App\Models\Advertise\AssetType;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CompressCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:compress {action=detect}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'compress';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //

        $action = $this->argument('action');
        if ($action == 'detect') {
            Log::info('detect start');
            $assets = Asset::where('id', '>=', 30)
                // ->whereNull('spec->size')
                ->get();
            $n = 0;
            foreach ($assets as $key => $asset) {
                // if ($n >= 100) {
                //     break;
                // }
                if (strpos($asset->url, 'mp4') && !isset($asset['spec']['size_per_second'])) {
                    $exist = Storage::disk('local')->exists($asset['file_path']);
                    $oldfile = Storage::disk('local')->path($asset['file_path']);
                    if (!$exist) {
                        $save = Storage::disk('local')->put($asset['file_path'], file_get_contents($asset['url']));
                    }

                    if (!isset($asset['spec']['size_per_second'])) {
                        $asset['spec'] =  array_merge($asset['spec'], [
                            'size_per_second' => round(filesize($oldfile) / round($asset['spec']['duration'], 1)),
                        ]);
                    }
                    if (!isset($asset['spec']['size'])) {
                        $asset['spec'] =  array_merge($asset['spec'], [
                            'size' => $this->fileSizeConvert(filesize($oldfile)),
                        ]);
                    }
                    $asset->save();
                    Log::info('detect  mp4' . $asset['id']);
                    dump($asset->toArray());
                    $n++;
                }
                if ((strpos($asset->url, 'jpg') || strpos($asset->url, 'png')) && !isset($asset['spec']['size_i'])) {
                    $exist = Storage::disk('local')->exists($asset['file_path']);
                    $oldfile = Storage::disk('local')->path($asset['file_path']);
                    if (!$exist) {
                        $save = Storage::disk('local')->put($asset['file_path'], file_get_contents($asset['url']));
                    }

                    if (!isset($asset['spec']['size'])) {
                        $asset['spec'] =  array_merge($asset['spec'], [
                            'size' => $this->fileSizeConvert(filesize($oldfile)),
                        ]);
                    }
                    if (!isset($asset['spec']['size_i'])) {
                        $asset['spec'] =  array_merge($asset['spec'], [
                            'size_i' => filesize($oldfile),
                        ]);
                    }
                    $asset->save();
                    Log::info('detect jpg' . $asset['id']);
                    dump($asset->toArray());
                    $n++;
                }
            }
            Log::info('detect end');
        } elseif ($action == 'compress') {
            Log::info('compress start');
            $assets = Asset::where('id', '>=', 30)
                // ->where('spec->size_per_second', '>', 250000)
                // ->whereNull('spec->size_compress')
                ->get();
            // dd( $assets->count(), app()->environment());
            $n = 0;
            foreach ($assets as $key => $asset) {
                if ($n >= 20) {
                    break;
                }
                if (strpos($asset->url, 'mp4')) {
                    if (
                        !isset($asset['spec']['size_compress'])
                        && isset($asset['spec']['size_per_second'])
                        && $asset['spec']['size_per_second'] > 180000
                    ) {
                        $oldfile = Storage::disk('local')->path($asset['file_path']);
                        $file_name = date('Ymd') . time() . uniqid() . "." . pathinfo($oldfile)['extension'];
                        $path = Storage::disk('local')->path('') . 'asset/';
                        $dir = 'asset/';
                        $newfile = $path . $file_name;
                        // exec("ffmpeg -y -i $oldfile -b 1000000 $newfile");
                        exec("ffmpeg -y -i $oldfile  -crf 32  $newfile");
                        $upload = Storage::put($dir . $file_name, file_get_contents($newfile));
                        // dump($video_info['bit_rate'], $upload);
                        $asset['hash'] = md5_file($newfile);
                        $asset['url'] = Storage::url($dir . $file_name);
                        $asset['spec'] =  array_merge($asset['spec'], [
                            'size_per_second_compress' => round(filesize($newfile) / round($asset['spec']['duration'], 1)),
                            'size_compress' => $this->fileSizeConvert(filesize($newfile)),
                            'file_path_compress' => $dir . $file_name,
                        ]);
                        $asset->save();
                        // $downloadfile = file_get_contents($asset['url']);
                        // unset($downloadfile);
                        Log::info('compress mp4   ' . $asset['id']);
                        dump($asset->toArray());
                        $n++;
                    }
                }

                if (strpos($asset->url, 'png') || strpos($asset->url, 'jpg')) {
                    if (
                        !isset($asset['spec']['size_compress'])
                        // && isset($asset['spec']['size_i'])
                        // && $asset['spec']['size_i'] > 200000
                    ) {
                        try {
                            $oldfile = Storage::disk('local')->path($asset['file_path']);
                            // $ext = strpos($asset->url, 'png')? 'png':'jpg';
                            $file_name = date('Ymd') . time() . uniqid() . "." . pathinfo($oldfile)['extension'];
                            $path = Storage::disk('local')->path('') . 'asset/';
                            $dir = 'asset/';
                            $newfile = $path . $file_name;

                            $tinifykey = config('app.tinify_key');
                            \Tinify\setKey($tinifykey);
                            $source = \Tinify\fromFile($oldfile);
                            $source->toFile($newfile);

                            $upload = Storage::put($dir . $file_name, file_get_contents($newfile));
                            // dump($video_info['bit_rate'], $upload);
                            $asset['hash'] = md5_file($newfile);
                            $asset['url'] = Storage::url($dir . $file_name);
                            $asset['spec'] =  array_merge($asset['spec'], [
                                'size_compress' => $this->fileSizeConvert(filesize($newfile)),
                                'file_path_compress' => $dir . $file_name,
                            ]);
                            $asset->save();
                            Log::info('compress jpg' . $asset['id']);
                            dump($asset->toArray());
                            $n++;
                        }
                        /* catch (\Tinify\AccountException $e) {
                            print("The error message is: " . $e->getMessage());
                            // Verify your API key and account limit.
                         catch (\Tinify\ServerException $e) {
                            // Temporary issue with the Tinify API.
                        } catch (\Tinify\ConnectionException $e) {
                            // A network connection error occurred.
                        }  */ 
                        catch (\Tinify\ClientException $e) {
                            $asset['spec'] =  array_merge($asset['spec'], [
                                'size_compress' => $asset['spec']['size_i'],
                                
                            ]);
                            $asset->save();
                            // Check your source image and request options.
                        }catch (\Exception $e) {
                            Log::error($asset);
                            Log::error($e);
                        }
                    }
                }
            }
            Log::info('compress end');
        }
    }

    public function  fileSizeConvert($bytes)
    {
        $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );

        foreach ($arBytes as $arItem) {
            if ($bytes >= $arItem["VALUE"]) {
                $result = $bytes / $arItem["VALUE"];
                // $result = str_replace(".", ",", strval(round($result, 2))) . " " . $arItem["UNIT"];
                $result =  strval(round($result, 2)) . " " . $arItem["UNIT"];
                break;
            }
        }
        return $result;
    }
}
