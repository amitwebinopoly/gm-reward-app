<?php

namespace App\Http\Middleware;

use App\Lib\AuthRedirection;
use App\Models\Session;
use Closure;
use Illuminate\Http\Request;
use Shopify\Utils;

class EnsureFrontendShopAuth
{
    /**
     * Checks if the shop in the query arguments is currently installed.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $res = [];
        if(isset($_GET['shop']) && !empty($_GET['shop'])){
            $shop = Utils::sanitizeShopDomain($_GET['shop']);
        }else if(isset($_POST['shop']) && !empty($_POST['shop'])){
            $shop = Utils::sanitizeShopDomain($_POST['shop']);
        }else {
            $res['success'] = 'false';
            $res['message'] = 'Invalid request. Shop is missing.';
            echo json_encode($res,1);exit;
        }

        $shop_db_data = Session::where('shop', $shop)->get()->toArray();
        if(isset($shop_db_data[0]['id']) && !empty($shop_db_data[0]['id'])){
            $request->attributes->set('shop_db_data', $shop_db_data[0]);
            return $next($request);
        }else{
            $res['success'] = 'false';
            $res['message'] = 'Invalid request. Shop is not found.';
            echo json_encode($res,1);exit;
        }
    }
}
