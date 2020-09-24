<?php
namespace App\Models\Advertise;

class ChannelPutMode
{
    const Normal = 1;
    const Backup = 2;

    public static function get($type_id){
        if(isset(self::$list[$type_id])) {
            return self::$list[$type_id];
        }
        return null;
    }

    public static $list = [
        self::Normal => [
            'id' => self::Normal,
            'name' => 'Normal',
        ],
        self::Backup => [
            'id' => self::Backup,
            'name' => 'Backup',
        ],
    ];
}
