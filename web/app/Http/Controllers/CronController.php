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

		$sql = 'SELECT * FROM order_logs WHERE status="0" LIMIT 2';
		$orders = $OrderLogsModel->select_raw_query($sql);
		if(!empty($orders)){
			foreach($orders as $order){
				$updateArr = ['status' => "1"];

				if($order->log_type == 'orders_create'){
					//get member_id from GM API
					$cw_data = $GMController->get_member_id_from_email($order->email);
					if(isset($cw_data['MemberNumber']) && !empty($cw_data['MemberNumber'])){
						$updateArr['gm_api_member_res'] = $cw_data['MemberNumber'];

						//add points in customer's ac
						$adjust_reward_amount_res = $GMController->adjust_reward_amount($cw_data['MemberNumber'],"D",$order->total_line_items_price);
						if(isset($adjust_reward_amount_res['RespCode']) && $adjust_reward_amount_res['RespCode']=="200"){
							$updateArr['gm_api_items_price_res'] = json_encode($adjust_reward_amount_res,1);
						}else{
							$updateArr['status'] = "9";
							$updateArr['gm_api_items_price_res'] = json_encode($adjust_reward_amount_res,1);
						}

						//request for redem points from customer's ac
						if($order->gm_discount_amount > 0){
							$adjust_reward_amount_res = $GMController->redemption_request($cw_data['MemberNumber'],$order->gm_discount_amount);
							if(isset($adjust_reward_amount_res['RespCode']) && $adjust_reward_amount_res['RespCode']=="200"){
								$updateArr['gm_api_discount_price_res'] = json_encode($adjust_reward_amount_res,1);
							}else{
								$updateArr['status'] = "9";
								$updateArr['gm_api_discount_price_res'] = json_encode($adjust_reward_amount_res,1);
							}
						}
					}else{
						$updateArr['status'] = "9";
						$updateArr['gm_api_member_res'] = @$cw_data['RespMessage'];
					}
				}
				else if($order->log_type == 'refunds_create'){
					//get member_id from GM API
					$cw_data = $GMController->get_member_id_from_email($order->email);
					if(isset($cw_data['MemberNumber']) && !empty($cw_data['MemberNumber'])){
						$updateArr['gm_api_member_res'] = $cw_data['MemberNumber'];

						//deduct points from customer's ac
						$adjust_reward_amount_res = $GMController->adjust_reward_amount($cw_data['MemberNumber'],"C",$order->total_line_items_price);
						if(isset($adjust_reward_amount_res['RespCode']) && $adjust_reward_amount_res['RespCode']=="200"){
							$updateArr['gm_api_items_price_res'] = json_encode($adjust_reward_amount_res,1);
						}else{
							$updateArr['status'] = "9";
							$updateArr['gm_api_items_price_res'] = json_encode($adjust_reward_amount_res,1);
						}

					}else{
						$updateArr['status'] = "9";
						$updateArr['gm_api_member_res'] = @$cw_data['RespMessage'];
					}
				}

				$OrderLogsModel->update_order_logs($order->id, $updateArr);
			}
		}else{
			echo 'No records found.';
		}


	}

}