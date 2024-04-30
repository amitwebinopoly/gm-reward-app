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

	public function create_discount_code(Request $request){
		$GMController = new GMController();
		$InexController = new InexController();
		$shop = $_POST['shop'];

		$validator = Validator::make($request->all(), [
			'email' => 'required|email',
			'amount' => 'required',
			'customer_id' => 'required',
			'variant_ids' => 'required',
		]);

		if (!$validator->fails()) {

			$shop_db_data = \App\Models\Session::where('shop', $shop)->get()->toArray();
			if(isset($shop_db_data) && !empty($shop_db_data)){
				$token = $shop_db_data[0]['access_token'];
				$headers = array(
					'X-Shopify-Access-Token' => $token
					//'X-Shopify-Storefront-Access-Token' => $storefront_access_token
				);
				$GraphqlController = new GraphqlController($shop, $headers, false); //pass true for store front apis

				//$cw_data = $GMController->get_member_id_from_email($_POST['email']);
				//if(isset($cw_data['MemberNumber']) && !empty($cw_data['MemberNumber'])){

					$discount_code = 'GMREWARD_'.$InexController->INEX_random_string();
					$variant_ids_arr = explode(',',$_POST['variant_ids']);
					$productVariantsToAddArr = [];
					foreach($variant_ids_arr as $var_id){
						$productVariantsToAddArr[] = '"gid://shopify/ProductVariant/'.$var_id.'"';
					}

					//date_default_timezone_set('America/New_York');
					$startsAt = date("Y-m-d\TH:i:s\Z");
					$endsAt = date("Y-m-d\TH:i:s\Z", strtotime($startsAt) + 3600);

					//create discount code
					$mutation = 'mutation discountCodeBasicCreate($basicCodeDiscount: DiscountCodeBasicInput!) {
					  discountCodeBasicCreate(basicCodeDiscount: $basicCodeDiscount) {
						codeDiscountNode { id }
						userErrors { field message }
					  }
					}';
					$input = '{
					  "basicCodeDiscount": {
						"appliesOncePerCustomer": true,
						"code": "'.$discount_code.'",
						"combinesWith": { "orderDiscounts": true },
						"customerGets": {
						  "items": {
							"products": {
							  "productVariantsToAdd": [ '.(implode(',',$productVariantsToAddArr)).' ]
							}
						  },
						  "value": { "discountAmount": { "amount": "'.bcdiv($_POST['amount'],1,2).'", "appliesOnEachItem": true } }
						},
						"customerSelection": {
						  "customers": { "add": [ "gid://shopify/Customer/'.$_POST['customer_id'].'" ] }
						},
						"startsAt": "'.$startsAt.'",
						"endsAt": "'.$endsAt.'",
						"title": "GM Reward Discount",
						"usageLimit": 1
					  }
					}';

					$dcRes = $GraphqlController->runByMutation($mutation,$input);
					if($dcRes['data']['discountCodeBasicCreate']['codeDiscountNode']['id']){
						$res['success'] = 'true';
						$res['message'] = '';
						$res['data'] = [
							'id' => $dcRes['data']['discountCodeBasicCreate']['codeDiscountNode']['id'],
							'discount_code' => $discount_code
						];
					}else{
						$res['success'] = 'false';
						$res['message'] = 'Something goes wrong while creating discount code.';
					}
				/*}else{
					$res['success'] = 'false';
					$res['message'] = @$cw_data['RespMessage'];
				}*/
			}
			else{
				$res['success'] = 'false';
				$res['message'] = 'Invalid shop';
			}
		}else{
			$res['success'] = 'false';
			foreach($validator->errors()->getMessages() as $err){
				$res['message'] = @$err[0];
			}
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