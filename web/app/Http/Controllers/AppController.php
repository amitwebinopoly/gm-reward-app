<?php namespace App\Http\Controllers;

use App\Models\CustomerDeleteLogsModel;
use App\Models\CustomerWalletModel;
use App\Models\CustomerWalletUsesModel;
use App\Http\Controllers\InexController;

use App\Models\OrderLogsModel;
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
	public function order_list_post(Request $request){
		$OrderLogsModel = new OrderLogsModel();

		$session = $request->get('shopifySession');
		$shop = $session->getShop();
		$token = $session->getAccessToken();

		$namespace = 'gm_rewards';
		$key = 'settings';

		$record_count=0;
		$page=0;
		$current_page=1;
		$rows='10';
		$keyword='';
		if( (isset($_POST['rows']))&&(!empty($_POST['rows'])) ){
			$rows=$_POST['rows'];
		}
		if( (isset($_POST['keyword']))&&(!empty($_POST['keyword'])) ){
			$keyword=$_POST['keyword'];
		}
		if( (isset($_POST['current_page']))&&(!empty($_POST['current_page'])) ){
			$current_page=$_POST['current_page'];
		}

		$cond_keyword = '';
		if(isset($keyword) && !empty($keyword)){
			$cond_keyword = " AND ( order_number = '".$keyword."'
			OR order_id = '".$keyword."'
			OR customer_email like '%".$keyword."%'
			OR customer_first_name like '%".$keyword."%'
			)";
		}

		$sql='SELECT count(DISTINCT id) as count
                FROM `order_logs`
                WHERE 1
                '.$cond_keyword.'
            ';
		//$all_count = $db->fetch_all($sql);
		$all_count = $OrderLogsModel->select_raw_query($sql);

		if($rows=='ALL'){
			$start=0;
			$end = $all_count[0]->count;
			$rows = $all_count[0]->count;
		}else{
			$start=($current_page-1)*$rows;
			$end=$rows;
		}

		$sql='
                SELECT id, order_id, order_number, email, total_line_items_price, gm_discount_amount, gm_discount_code, gm_api_member_res,
                customer_first_name, customer_last_name, customer_address, customer_phone, customer_email, items, add_date
                FROM `order_logs`
                WHERE 1
                '.$cond_keyword.'
                ORDER BY id DESC
                LIMIT '.$start.','.$end.'
            ';
		$all_list = $OrderLogsModel->select_raw_query($sql);

		if( (isset($all_count[0]->count))&&(!empty($all_count[0]->count)) ){
			$record_count=$all_count[0]->count;
			$page=$record_count/$rows;
			$page=ceil($page);
		}
		$sr_start=0;
		if($record_count>=1){
			$sr_start=(($current_page-1)*$rows)+1;
		}
		$sr_end=($current_page)*$rows;
		if($record_count<=$sr_end){
			$sr_end=$record_count;
		}

		if(isset($_POST['pagination_export']) && $_POST['pagination_export']=='Y'){
			$headers = array(
				'X-Shopify-Access-Token' => $token
				//'X-Shopify-Storefront-Access-Token' => $storefront_access_token
			);
			$GraphqlController = new GraphqlController($shop, $headers, false); //pass true for store front apis

			//check metafield exist or not
			$query = '{ shop { id metafield(namespace:"'.$namespace.'",key:"'.$key.'"){ id value } } }';
			$shop_data = $GraphqlController->runByQuery($query);

			$points_exchange = '';
			if(isset($shop_data['data']['shop']['metafield']['value']) && !empty($shop_data['data']['shop']['metafield']['value']) ){
				$arr = json_decode($shop_data['data']['shop']['metafield']['value'],1);
				if(isset($arr['points_exchange']) && !empty($arr['points_exchange'])){
					$points_exchange = $arr['points_exchange'];
				}
			}

			$res = [];
			if(isset($all_list) && !empty($all_list)){
				$export_file = time() . '-export.csv';
				//$upload_dir = public_path().'/..'.Config::get('constant.ASSETS_LOCATION').Config::get('constant.EXPORT_CUSTOMERS_LOCATION');
				$upload_dir = public_path().'/'.Config::get('constant.EXPORT_ORDERS_LOCATION');
				$export_file_url = asset(Config::get('constant.EXPORT_ORDERS_LOCATION').$export_file);

				$export_file_path = $upload_dir . $export_file;
				$file_for_export_data = fopen($export_file_path,"w");

				fputcsv($file_for_export_data,['Order','Member ID','Customer','Email','Address','Phone','Total net $','Discount','Discount Point','Product','SKU','Unit Price','Date']);
				foreach($all_list as $single){
					$item_arr = [];
					if(!empty($single->items)){
						$item_arr = json_decode($single->items,1);
					}

					fputcsv($file_for_export_data,array(
						'=("'.$single->order_number.'")',
						$single->gm_api_member_res,
						$single->customer_first_name.' '.$single->customer_last_name,
						$single->customer_email,
						$single->customer_address,
						$single->customer_phone,
						$single->total_line_items_price,
						(!empty($single->gm_discount_code))?($single->gm_discount_code.' - $'.$single->gm_discount_amount):"",
						(!empty($points_exchange) && !empty($single->gm_discount_amount))?($points_exchange*$single->gm_discount_amount):"",
						@$item_arr[0]['title'],
						@$item_arr[0]['sku'],
						'$'.(@$item_arr[0]['price']*@$item_arr[0]['quantity']),
						$single->add_date
					));

					for($i=1;$i<count($item_arr);$i++){
						fputcsv($file_for_export_data,array(
							'',
							'',
							'',
							'',
							'',
							'',
							'',
							'',
							'',
							@$item_arr[$i]['title'],
							@$item_arr[$i]['sku'],
							'$'.(@$item_arr[$i]['price']*@$item_arr[$i]['quantity']),
							''
						));
					}
				}

				fclose($file_for_export_data);

				$res['success'] = 'true';
				$res['message'] = '';
				$res['export_url'] = $export_file_url;
			}else{
				$res['success'] = 'false';
				$res['message'] = 'Records are not available.';
			}
		}else{
			$res['success']='true';
			$res['data'] = $all_list;
			$res['page_count'] = $page;
			$res['record_count']=$record_count;
			$res['sr_start']=$sr_start;
			$res['sr_end']=$sr_end;
			$res['shop']=$shop;
		}
		echo json_encode($res,1);

	}

}