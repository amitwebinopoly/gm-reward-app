<?php namespace App\Http\Controllers;

use App\Http\Controllers\InexController;
use App\Http\Controllers\GraphqlController;
use App\Http\Controllers\GMController;

//use App\Models\OrderItemModel;
//use App\Models\OrderModel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use PHPUnit\Framework\Exception;
use Shopify\Clients\Graphql;
use Shopify\Clients\Rest;

class FrontEndController extends Controller {

	public $param=array();
	public $response=array();

	public function __construct()
	{
		/*$this->middleware(function ($request, $next) {
			parent::login_user_details();
			return $next($request);
		});*/
	}

	public function get_cust_point(){
		if(isset($_POST['email']) && !empty($_POST['email']) ){
			$GMController = new GMController();
			$cw_data = $GMController->get_member_id_from_email($_POST['email']);
			if(isset($cw_data['MemberNumber']) && !empty($cw_data['MemberNumber'])){
				$wallet_data = $GMController->get_member_data_from_member_id($cw_data['MemberNumber']);
				if(isset($wallet_data)){
					$res['success'] = 'true';
					$res['message'] = @$wallet_data['RespMessage'];
					$res['data'] = [
						'total_dollars' => @$wallet_data['LOY Member']['Total Dollars'],
						'total_points' => @$wallet_data['LOY Member']['Total Points'],
						'next_tier' => @$wallet_data['LOY Member']['Tier']['Next Tier Name'],
						'current_tier' => @$wallet_data['LOY Member']['Tier']['Current Tier Name'],
					];
				}else{
					$res['success'] = 'false';
					$res['message'] = @$wallet_data['RespMessage'];
				}
			}else{
				$res['success'] = 'false';
				$res['message'] = @$cw_data['RespMessage'];
			}
		}else{
			$res['success'] = 'false';
			$res['message'] = 'Invalid request.';
		}
		echo json_encode($res,1);
	}

	public function adjust_amount(Request $request){
		$validator = Validator::make($request->all(), [
			'email' => 'required|email',
			'amount' => 'required',
			'credit_or_debit' => 'required|in:C,D',
		]);

		if (!$validator->fails()) {
			$GMController = new GMController();
			$cw_data = $GMController->get_member_id_from_email($_POST['email']);
			if(isset($cw_data['MemberNumber']) && !empty($cw_data['MemberNumber'])){
				$adjust_reward_amount_res = $GMController->adjust_reward_amount($cw_data['MemberNumber'],$_POST['credit_or_debit'],$_POST['amount']);
				if(isset($adjust_reward_amount_res['RespCode']) && $adjust_reward_amount_res['RespCode']=="200"){
					$res['success'] = 'true';
					$res['message'] = @$adjust_reward_amount_res['RespMessage'];
				}else{
					$res['success'] = 'false';
					$res['message'] = @$adjust_reward_amount_res['RespMessage'];
				}

			}else{
				$res['success'] = 'false';
				$res['message'] = @$cw_data['RespMessage'];
			}
		}else{
			$res['success'] = 'false';
			foreach($validator->errors()->getMessages() as $err){
				$res['message'] = @$err[0];
			}
		}

		echo json_encode($res,1);
	}

}