<?php
/**
 * Created by PhpStorm.
 * User: Dev
 * Date: 2019/11/26
 * Time: 11:25
 */
namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait MultiTable{
    public static function multiTableQuery(\Closure $fun_build_query, $start_date, $end_date){
        $start = Carbon::parse($start_date);
        $end = Carbon::parse($end_date);
        $queries = collect();
        $main_table_added = false;
        $base_table_name = (new self())->getTable();
        for ($index = $start->copy(); $index->format('Ymd') <= $end->format('Ymd'); $index->addDay()) {
            $table_name = "{$base_table_name}_{$index->format('Ymd')}";
            if(Schema::hasTable($table_name)){
                $queries->push(
                    $fun_build_query(DB::table($table_name))
                );
            }else{
                if(!$main_table_added){
                    $main_table_name = "z{$base_table_name}";
                    $queries->push(
                        $fun_build_query(DB::table($main_table_name))
                    );
                }
                $main_table_added = true;
            }
        }
        if($queries->count() == 0){
            throw new \Exception('multi table not exists');
        }

        $unionQuery = $queries->shift();
        $queries->each(function ($item, $key) use ($unionQuery) {
            $unionQuery->unionAll($item);
        });

        $multi_table_query = (new self())->setTable('union_table')
            ->from(DB::raw("({$unionQuery->toSql()}) as {$base_table_name}"))
            ->mergeBindings($unionQuery);

        return $multi_table_query;
    }
}