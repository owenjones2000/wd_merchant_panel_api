<?php
/**
 * Created by PhpStorm.
 * User: Dev
 * Date: 2020/3/26
 * Time: 13:57
 */

namespace App\Http\Middleware;

use App\User;
use Closure;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Product
{
    public function handle($request, Closure $next, $product)
    {
        /** @var User $op_user */
        $op_user = auth()->user();
        if(empty($op_user['currentMainUser'])){
            throw new HttpException(403, 'Please select the advertiser of the service');
        }
        if($product == 'advertise'){
            if(!$op_user['currentMainUser']['isAdvertiseEnabled']) {
                throw new HttpException(403,
                    'The selected account has no permission to advertise');
            }
        } else if($product == 'publish'){
            if(!$op_user['currentMainUser']['isPublishEnabled']) {
                throw new HttpException(403,
                    'The selected account has no permission to publish');
            }
        } else {
            throw new HttpException(403,
                'The selected account has no permission');
        }
        return $next($request);
    }
}