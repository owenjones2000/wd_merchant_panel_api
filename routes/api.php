<?php

use Illuminate\Http\Request;
use \App\Laravue\Faker;
use \App\Laravue\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::group(['middleware' => 'api'], function () {
    Route::post('auth/login', 'AuthController@login');
    Route::group(['middleware' => 'auth:api'], function () {
        Route::get('auth/user', 'AuthController@user');
        Route::post('auth/logout', 'AuthController@logout');
    });


    Route::group(['namespace' => 'Home',], function () {
        //后台布局
        Route::get('/', 'IndexController@layout')->name('home.layout');
        // 控制台
        Route::group([/*'middleware' => ''*/], function () {
            Route::get('data', 'DashBoardController@data')->name('home.dashboard.data');
            Route::get('dashboard', 'DashBoardController@view')->name('home.dashboard.view');

        });
        //图标
        Route::get('icons', 'IndexController@icons')->name('home.icons');
        //切换广告主
        Route::post('user/change', 'UserController@changeMainUser')->name('home.user.change');
        //个人信息编辑
        Route::get('user/edit', 'UserController@edit')->name('home.user.edit');
        Route::post('user/update', 'UserController@update')->name('home.user.update');
    });

    //投放管理
    Route::group(['namespace' => 'Advertise', 'prefix' => 'advertise', 'middleware' => ['operation.log', 'permission:advertise.manage', 'product:advertise']], function () {

        // 应用管理
        Route::group(['prefix' => 'app', 'middleware' => 'permission:advertise.app'], function () {
            Route::get('data', 'AppController@data')->name('advertise.app.data');
            Route::get('list', 'AppController@index')->name('advertise.app');

            //编辑
            Route::get('{id?}', 'AppController@edit')->name('advertise.app.edit')->middleware('permission:advertise.app')
            ->where('id', '\d+');
            Route::post('{id?}', 'AppController@save')->name('advertise.app.save')->middleware('permission:advertise.app.edit')
            ->where('id', '\d+');
            Route::post('{id}/enable', 'AppController@enable')->name('advertise.app.enable')->middleware('permission:advertise.app.edit');
            Route::post('{id}/disable', 'AppController@disable')->name('advertise.app.disable')->middleware('permission:advertise.app.edit');
            Route::post('icon', 'AppController@uplodeIcon')->name('advertise.app.icon')->middleware('permission:advertise.app.edit');

            Route::post('{app_id}/channel/{channel_id}/enable', 'ChannelController@enable')->name('advertise.campaign.channel.enable')->middleware('permission:advertise.campaign.edit');
            Route::post('{app_id}/channel/{channel_id}/disable', 'ChannelController@disable')->name('advertise.campaign.channel.disable')->middleware('permission:advertise.campaign.edit');

            Route::group(['prefix' => '{app_id}/campaign', 'middleware' => 'permission:advertise.campaign'], function () {
                Route::get('list', 'CampaignController@appBelonglist')->name('advertise.campaign');
            });

            //删除
            //        Route::delete('destroy', 'AppController@destroy')->name('advertise.app.destroy')->middleware('permission:advertise.app.destroy');
        });

        // 活动管理
        Route::group(['prefix' => 'campaign', 'middleware' => 'permission:advertise.campaign'], function () {
            Route::get('data', 'CampaignController@data')->name('advertise.campaign.data');
            Route::get('alldata', 'CampaignController@alldata')->name('advertise.campaign.alldata');
            Route::get('list', 'CampaignController@list')->name('advertise.campaign');
            Route::get('performance', 'CampaignController@performance')->name('advertise.campaign.performance');
            Route::get('performance-export', 'CampaignController@performanceExport')->name('advertise.campaign.export');
            Route::get('performance-data', 'CampaignController@performanceData')->name('advertise.campaign.performance-data');
            //编辑
            Route::get('{id?}', 'CampaignController@edit')->name('advertise.campaign.edit')->middleware('permission:advertise.campaign.edit');
            Route::post('{id?}', 'CampaignController@save')->name('advertise.campaign.save')->middleware('permission:advertise.campaign.edit');
            Route::post('{id}/enable', 'CampaignController@enable')->name('advertise.campaign.enable')->middleware('permission:advertise.campaign.edit');
            Route::post('{id}/disable', 'CampaignController@disable')->name('advertise.campaign.disable')->middleware('permission:advertise.campaign.edit');
            //删除
            //        Route::delete('destroy', 'CampaignController@destroy')->name('advertise.campaign.destroy')->middleware('permission:advertise.campaign.destroy');

            // 广告
            Route::group(['prefix' => '{campaign_id}/ad', 'middleware' => 'permission:advertise.campaign.ad'], function () {
                Route::get('data', 'AdController@data')->name('advertise.campaign.ad.data');
                // Route::get('alldata', 'AdController@alldata')->name('advertise.campaign.ad.alldata');
                Route::get('list', 'AdController@list')->name('advertise.campaign.ad');
                //编辑
                Route::get('{id?}', 'AdController@edit')->name('advertise.campaign.ad.edit')->middleware('permission:advertise.campaign.ad');
                Route::post('{id?}', 'AdController@save')->name('advertise.campaign.ad.save')->middleware('permission:advertise.campaign.ad.edit');
                Route::post('{id}/enable', 'AdController@enable')->name('advertise.ad.enable')->middleware('permission:advertise.campaign.ad.edit');
                Route::post('{id}/disable', 'AdController@disable')->name('advertise.ad.disable')->middleware('permission:advertise.campaign.ad.edit');
                Route::post('{id}/clone', 'AdController@cloneAd')->name('advertise.ad.clone')->middleware('permission:advertise.campaign.ad.edit');
                Route::get('{id}/editclone', 'AdController@editClone')->name('advertise.ad.getclone')->middleware('permission:advertise.campaign.ad.edit');
                //删除
                //            Route::delete('destroy', 'AdController@destroy')->name('advertise.campaign.ad.destroy')->middleware('permission:advertise.campaign.ad.destroy');
            });

            // 子渠道
            Route::group(['prefix' => '{campaign_id}/channel', 'middleware' => 'permission:advertise.campaign'], function () {
                Route::get('data', 'ChannelController@data')->name('advertise.campaign.channel.data');
                Route::get('list', 'ChannelController@list')->name('advertise.campaign.channel');
                Route::post('{channel_id}/enable', 'ChannelController@enable')->name('advertise.campaign.channel.v2.enable');
                Route::post('{channel_id}/disable', 'ChannelController@disable')->name('advertise.campaign.channel.v2.disable');
            });

            // 区域
            Route::group(['prefix' => '{campaign_id}/region', 'middleware' => 'permission:advertise.campaign'], function () {
                Route::get('data', 'RegionController@data')->name('advertise.campaign.region.data');
                Route::get('channle/data', 'RegionController@channelData')->name('advertise.campaign.region.channel.data')->middleware('permission:advertise.campaign.optimization');
                Route::get('list', 'RegionController@list')->name('advertise.campaign.region');
                Route::get('channel/list', 'RegionController@channelList')->name('advertise.campaign.region.channel')->middleware('permission:advertise.campaign.optimization');;
                Route::post('channel', 'RegionController@channelBid')->name('advertise.campaign.region.channel.bid')->middleware('permission:advertise.campaign.optimization');;
                Route::post('channel/reset', 'RegionController@channelResetBid')->name('advertise.campaign.region.channel.bid.reset')->middleware('permission:advertise.campaign.optimization');
            });
        });

        //文件
        Route::post('Asset', 'AssetController@processMediaFiles')->name('advertise.asset.process'); // 素材
    });


    // 变现管理
    Route::group(['namespace' => 'Publish', 'prefix' => 'publish', 'middleware' => ['operation.log', 'permission:publish.manage', 'product:publish']], function () {
        Route::get('dashboard-data', 'AppController@dashboardData')->name('publish.app.dashboard.data')->middleware('permission:publish.app.dashboard');
       
        // 应用管理
        Route::group(['prefix' => 'app', 'middleware' => 'permission:publish.app'], function () {
            Route::get('data', 'AppController@data')->name('publish.app.data');
            Route::get('list', 'AppController@list')->name('publish.app');
            Route::get('listdata', 'AppController@listdata')->name('publish.app.listdata');
            Route::get('performance', 'AppController@performance')->name('publish.app.performance');
           
            Route::get('dashboard', 'AppController@dashboard')->name('publish.app.dashboard')->middleware('permission:publish.app.dashboard');
            //编辑
            Route::get('{id?}', 'AppController@edit')->name('publish.app.edit')->middleware('permission:publish.app')
            ->where('id', '\d+');
            Route::post('{id?}', 'AppController@save')->name('publish.app.save')->middleware('permission:publish.app.edit')
            ->where('id', '\d+');
            Route::post('{id}/enable', 'AppController@enable')->name('publish.app.enable')->middleware('permission:publish.app.edit');
            Route::post('{id}/disable', 'AppController@disable')->name('publish.app.disable')->middleware('permission:publish.app.edit');
            Route::post('icon', 'AppController@uplodeIcon')->name('publish.app.icon')->middleware('permission:publish.app.edit');

            Route::post('{app_id}/channel/{channel_id}/enable', 'ChannelController@enable')->name('publish.campaign.channel.enable')->middleware('permission:publish.campaign.edit');
            Route::post('{app_id}/channel/{channel_id}/disable', 'ChannelController@disable')->name('publish.campaign.channel.disable')->middleware('permission:publish.campaign.edit');
            // 区域
            Route::group(['prefix' => '{campaign_id}/region', 'middleware' => 'permission:publish.campaign'], function () {
                Route::get('data', 'RegionController@data')->name('publish.campaign.region.data');
                Route::get('list', 'RegionController@list')->name('publish.campaign.region');
            });

            //删除
            //        Route::delete('destroy', 'AppController@destroy')->name('advertise.app.destroy')->middleware('permission:advertise.app.destroy');
        });
    });

    Route::apiResource('users', 'UserController')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
    Route::get('users/{user}/permissions', 'UserController@permissions')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_PERMISSION_MANAGE);
    Route::put('users/{user}/permissions', 'UserController@updatePermissions')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_PERMISSION_MANAGE);
    Route::apiResource('roles', 'RoleController')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_PERMISSION_MANAGE);
    Route::get('roles/{role}/permissions', 'RoleController@permissions')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_PERMISSION_MANAGE);
    Route::apiResource('permissions', 'PermissionController')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_PERMISSION_MANAGE);

    
    
    
    
    
    
    // Fake APIs
    Route::get('/table/list', function () {
        $rowsNumber = mt_rand(20, 30);
        $data = [];
        for ($rowIndex = 0; $rowIndex < $rowsNumber; $rowIndex++) {
            $row = [
                'author' => Faker::randomString(mt_rand(5, 10)),
                'display_time' => Faker::randomDateTime()->format('Y-m-d H:i:s'),
                'id' => mt_rand(100000, 100000000),
                'pageviews' => mt_rand(100, 10000),
                'status' => Faker::randomInArray(['deleted', 'published', 'draft']),
                'title' => Faker::randomString(mt_rand(20, 50)),
            ];

            $data[] = $row;
        }

        return response()->json(new JsonResponse(['items' => $data]));
    });

    Route::get('/orders', function () {
        $rowsNumber = 8;
        $data = [];
        for ($rowIndex = 0; $rowIndex < $rowsNumber; $rowIndex++) {
            $row = [
                'order_no' => 'LARAVUE' . mt_rand(1000000, 9999999),
                'price' => mt_rand(10000, 999999),
                'status' => Faker::randomInArray(['success', 'pending']),
            ];

            $data[] = $row;
        }

        return response()->json(new JsonResponse(['items' => $data]));
    });

    Route::get('/articles', function () {
        $rowsNumber = 10;
        $data = [];
        for ($rowIndex = 0; $rowIndex < $rowsNumber; $rowIndex++) {
            $row = [
                'id' => mt_rand(100, 10000),
                'display_time' => Faker::randomDateTime()->format('Y-m-d H:i:s'),
                'title' => Faker::randomString(mt_rand(20, 50)),
                'author' => Faker::randomString(mt_rand(5, 10)),
                'comment_disabled' => Faker::randomBoolean(),
                'content' => Faker::randomString(mt_rand(100, 300)),
                'content_short' => Faker::randomString(mt_rand(30, 50)),
                'status' => Faker::randomInArray(['deleted', 'published', 'draft']),
                'forecast' => mt_rand(100, 9999) / 100,
                'image_uri' => 'https://via.placeholder.com/400x300',
                'importance' => mt_rand(1, 3),
                'pageviews' => mt_rand(10000, 999999),
                'reviewer' => Faker::randomString(mt_rand(5, 10)),
                'timestamp' => Faker::randomDateTime()->getTimestamp(),
                'type' => Faker::randomInArray(['US', 'VI', 'JA']),

            ];

            $data[] = $row;
        }

        return response()->json(new JsonResponse(['items' => $data, 'total' => mt_rand(1000, 10000)]));
    });

    Route::get('articles/{id}', function ($id) {
        $article = [
            'id' => $id,
            'display_time' => Faker::randomDateTime()->format('Y-m-d H:i:s'),
            'title' => Faker::randomString(mt_rand(20, 50)),
            'author' => Faker::randomString(mt_rand(5, 10)),
            'comment_disabled' => Faker::randomBoolean(),
            'content' => Faker::randomString(mt_rand(100, 300)),
            'content_short' => Faker::randomString(mt_rand(30, 50)),
            'status' => Faker::randomInArray(['deleted', 'published', 'draft']),
            'forecast' => mt_rand(100, 9999) / 100,
            'image_uri' => 'https://via.placeholder.com/400x300',
            'importance' => mt_rand(1, 3),
            'pageviews' => mt_rand(10000, 999999),
            'reviewer' => Faker::randomString(mt_rand(5, 10)),
            'timestamp' => Faker::randomDateTime()->getTimestamp(),
            'type' => Faker::randomInArray(['US', 'VI', 'JA']),

        ];

        return response()->json(new JsonResponse($article));
    });

    Route::get('articles/{id}/pageviews', function ($id) {
        $pageviews = [
            'PC' => mt_rand(10000, 999999),
            'Mobile' => mt_rand(10000, 999999),
            'iOS' => mt_rand(10000, 999999),
            'android' => mt_rand(10000, 999999),
        ];
        $data = [];
        foreach ($pageviews as $device => $pageview) {
            $data[] = [
                'key' => $device,
                'pv' => $pageview,
            ];
        }

        return response()->json(new JsonResponse(['pvData' => $data]));
    });

});
