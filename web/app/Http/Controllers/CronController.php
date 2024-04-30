<?php namespace App\Http\Controllers;

use App\Http\Controllers\GMController;
//use App\Http\Controllers\ThirdPartyAPI\CarrierController;

/*use App\Http\Controllers\Admin\SubscribeController;
use App\Http\Controllers\Admin\UsersController;
use App\Property;
use App\PropertyRequest;
use App\State;
use App\SubscribeEmail;
use App\User;
use App\UsersOtp;*/
use App\Models\OrderLogsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class CronController extends Controller {

	public $param=array();
	public $response=array();

	public function __construct(){
		/*$this->middleware(function ($request, $next) {
			parent::login_user_details();
			return $next($request);
		});*/
	}

	public function process_order(){
		$GMController = new GMController();
		$OrderLogsModel = new OrderLogsModel();

		$sql = 'SELECT * FROM order_logs WHERE status="0"';
		$orders = $OrderLogsModel->select_raw_query($sql);
		if(!empty($orders)){
			foreach($orders as $order){
				$updateArr = ['status' => "1"];
				$cw_data = $GMController->get_member_id_from_email($order->email);
				if(isset($cw_data['MemberNumber']) && !empty($cw_data['MemberNumber'])){
					$updateArr['gm_api_member_res'] = $cw_data['MemberNumber'];

					$adjust_reward_amount_res = $GMController->adjust_reward_amount($cw_data['MemberNumber'],"D",$order->total_line_items_price);
					if(isset($adjust_reward_amount_res['RespCode']) && $adjust_reward_amount_res['RespCode']=="200"){
						$updateArr['gm_api_items_price_res'] = @$adjust_reward_amount_res['RespMessage'];
					}else{
						$updateArr['status'] = "9";
						$updateArr['gm_api_items_price_res'] = @$adjust_reward_amount_res['RespMessage'];
					}

					if($order->gm_discount_amount > 0){
						$adjust_reward_amount_res = $GMController->adjust_reward_amount($cw_data['MemberNumber'],"C",$order->gm_discount_amount);
						if(isset($adjust_reward_amount_res['RespCode']) && $adjust_reward_amount_res['RespCode']=="200"){
							$updateArr['gm_api_discount_price_res'] = @$adjust_reward_amount_res['RespMessage'];
						}else{
							$updateArr['status'] = "9";
							$updateArr['gm_api_discount_price_res'] = @$adjust_reward_amount_res['RespMessage'];
						}
					}
				}else{
					$updateArr['status'] = "9";
					$updateArr['gm_api_member_res'] = @$cw_data['RespMessage'];
				}

				$OrderLogsModel->update_order_logs($order->id, $updateArr);
			}
		}else{
			echo 'No records found.';
		}


	}

}