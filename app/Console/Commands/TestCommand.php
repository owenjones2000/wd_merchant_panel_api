<?php

namespace App\Console\Commands;

use App\Models\Advertise\Ad;
use App\User;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dcat\EasyExcel\Excel;
use Illuminate\Cache\RedisLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test {function} {param1?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'test';

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
        $p = $this->argument('function');
        $name = 'test' . $p;
        call_user_func([$this, $name]);
    }

    public function test1()
    {
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => env('FFMPEG_BIN_PATH', '/usr/local/bin/ffmpeg'),
            'ffprobe.binaries' => env('FFPROBE_BIN_PATH', '/usr/local/bin/ffprobe')
        ]);
        $ffprobe = FFProbe::create([
            'ffmpeg.binaries' => env('FFMPEG_BIN_PATH', '/usr/local/bin/ffmpeg'),
            'ffprobe.binaries' => env('FFPROBE_BIN_PATH', '/usr/local/bin/ffprobe')
        ]);
        $inputVideo = storage_path('app/asset/2020061815924445655eeac6952713e.mp4');
        $outputVideo = storage_path('app/asset/2crf32.mp4');
        $output = [];
        $tinifykey = config('app.tinify_key');

        // \Tinify\setKey($tinifykey);
        // $compressionsThisMonth = \Tinify\compressionCount();
        // dd($compressionsThisMonth);
        // $source = \Tinify\fromFile($inputVideo);
        // $source = \Tinify\fromUrl("https://tinypng.com/images/panda-happy.png");
        // $source->toFile($outputVideo);

        // ffprobe -hide_banner -v quiet -print_format json -show_format -show_streams
        // exec("ffmpeg -i $inputVideo -b 500000 $outputVideo");
        // exec("ffmpeg -i $inputVideo -c:v libx264 -crf 30 -c:a aac $outputVideo");
        exec("ffmpeg -i $inputVideo  -crf 32  $outputVideo", $output);
        // exec("ffmpeg  -hide_banner -i $inputVideo   -pix_fmt rgb24  $outputVideo");
        // exec("ffmpeg  -hide_banner -i $inputVideo   -pix_fmt pal8  $outputVideo");
        // exec("ffmpeg  -i $inputVideo   -pix_fmt rgba  $outputVideo");
        return $outputVideo;
    }

    public function test2()
    {
        $res = DB::table('a_asset')->select('id', 'spec')->whereNull('spec->duration')->orderBy('spec->size', 'desc')->toSql();
        dd($res);
    }

    public function test3()
    {
        $allSheets = Excel::import(storage_path('app/PopularObjects-2020-06-17-06-01-41.csv'))->toArray();
        // dd($allSheets);
        // $objects = array_column($allSheets[0], 'Object');
        $objects = array_column($allSheets[0], 'Object');
        $objects = array_map(function ($v) {
            return trim($v, '/');
        }, $objects);
        // foreach ($allSheets as $key => $value) {
        //     # code...
        //     $objects[] = $value['Object'];
        // }
        // dd($objects);
        $res = DB::table('a_asset')->select('id', 'spec')->whereIn('file_path', $objects)->get()->toArray();
        dd($res);
    }

    public function test4()
    {
        $ad_id = DB::select('select id from a_ad a left JOIN a_ad_country b on a.id=b.ad_id where country is null;');
        foreach ($ad_id as $key => $value) {
            $ad = Ad::find($value->id);
            $ad->regions()->syncWithoutDetaching(['ALL']);
        }
        dump($ad_id);
    }

    public function test5()
    {
        $user = User::find(3);
        $token = JWTAuth::fromUser($user);
        dd($token);
    }

    public function test6()
    {
        // dd(Redis::set('order', 1));

        $lock = Cache::lock('orderid', 10);
        dump(time());
        // dd($lock->get());
        if (!$lock->get()) {
            // 获取锁定10秒...
            throw new \Exception("请勿重复提交");
        }
        sleep(10);
        $lock->release();
    }
    public function test7()
    {

        Cache::lock('foo')->get(function () {
            // 获取无限期锁并自动释放...
            sleep(10);
        });
    }
}
