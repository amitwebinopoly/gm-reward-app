<?php namespace App\Http\Controllers;

use App\Models\CustomerDeleteLogsModel;
use App\Models\CustomerWalletModel;
use App\Models\CustomerWalletUsesModel;
use App\Http\Controllers\InexController;

use App\Models\ShippingAddressModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Validator;
use Shopify\Clients\Graphql;
use Shopify\Clients\Rest;

class AppController extends Controller {

	public $param=array();
	public $response=array();

	public function __construct()
	{
		/*$this->middleware(function ($request, $next) {
			parent::login_user_details();
			return $next($request);
		});*/
	}

	public function fetch_point_conversion_rate(Request $request){
		$res = [];

		$namespace = 'gm_rewards';
		$key = 'settings';

		$session = $request->get('shopifySession');
		$shop = $session->getShop();
		$token = $session->getAccessToken();

		$headers = array(
			'X-Shopify-Access-Token' => $token
			//'X-Shopify-Storefront-Access-Token' => $storefront_access_token
		);
		$GraphqlController = new GraphqlController($shop, $headers, false); //pass true for store front apis

		//check metafield exist or not
		$query = '{ shop { id metafield(namespace:"'.$namespace.'",key:"'.$key.'"){ id value } } }';
		$shop_data = $GraphqlController->runByQuery($query);

		if(isset($shop_data['data']['shop']['metafield']['value']) && !empty($shop_data['data']['shop']['metafield']['value']) ){
			$arr = json_decode($shop_data['data']['shop']['metafield']['value'],1);
			if(isset($arr['points_exchange']) && !empty($arr['points_exchange'])){
				$res['success']='true';
				$res['message']='';
				$res['data']=[
					'point_conversion_rate' => $arr['points_exchange']
				];
			}else{
				$res['success']='false';
				$res['message']='No rate is found.';
			}
		}else{
			$res['success']='false';
			$res['message']='Something went wrong while fetching rate.';
		}

		echo json_encode($res,1);
	}
	public function post_point_conversion_rate(Request $request){
		$res = [];

		$namespace = 'gm_rewards';
		$key = 'settings';

		$session = $request->get('shopifySession');
		$shop = $session->getShop();
		$token = $session->getAccessToken();

		$point_conversion_rate = $_POST['point_conversion_rate'];

		$headers = array(
			'X-Shopify-Access-Token' => $token
			//'X-Shopify-Storefront-Access-Token' => $storefront_access_token
		);
		$GraphqlController = new GraphqlController($shop, $headers, false); //pass true for store front apis

		$validator = Validator::make($request->all(), [
			'point_conversion_rate' => 'required'
		]);
		if ($validator->fails()) {
			$msg = '';
			$errors = $validator->errors();
			$allErrors = $errors->all();

			foreach($allErrors as $err){
				$msg = $err;
			}
			$res['success']='false';
			$res['message']=$msg;
			echo json_encode($res,1);
			exit;
		}

		//check metafield exist or not
		$query = '{ shop { id metafield(namespace:"'.$namespace.'",key:"'.$key.'"){ id } } }';
		$shop_data = $GraphqlController->runByQuery($query);

		$mutation = 'mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
			  metafieldsSet(metafields: $metafields) {
				metafields { key value }
				userErrors { field message }
			  }
			}';
		$input = '{
			  "metafields": [
				{
				  "key": "'.$key.'",
				  "namespace": "'.$namespace.'",
				  "ownerId": "'.@$shop_data['data']['shop']['id'].'",
				  "type": "json",
				  "value": "{\"points_exchange\":\"'.$point_conversion_rate.'\"}"
				}
			  ]
			}';
		$metaRes = $GraphqlController->runByMutation($mutation,$input);
		if(isset($metaRes['data']['metafieldsSet']['metafields']) && !empty($metaRes['data']['metafieldsSet']['metafields']) ){
			$res['success']='true';
			$res['message']='Rate saved successfully.';
		}else{
			$res['success']='false';
			$res['message']='Something went wrong while saving rate.';
		}


		echo json_encode($res,1);
	}

}