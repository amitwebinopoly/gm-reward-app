<?php

use App\Exceptions\ShopifyProductCreatorException;
use App\Lib\AuthRedirection;
use App\Lib\EnsureBilling;
use App\Lib\ProductCreator;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Shopify\Auth\OAuth;
use Shopify\Auth\Session as AuthSession;
use Shopify\Clients\HttpHeaders;
use Shopify\Clients\Rest;
use Shopify\Context;
use Shopify\Exception\InvalidWebhookException;
use Shopify\Utils;
use Shopify\Webhooks\Registry;
use Shopify\Webhooks\Topics;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
| If you are adding routes outside of the /api path, remember to also add a
| proxy rule for them in web/frontend/vite.config.js
|
*/

Route::fallback(function (Request $request) {
    if (Context::$IS_EMBEDDED_APP &&  $request->query("embedded", false) === "1") {
        if (env('APP_ENV') === 'production') {
            return file_get_contents(public_path('index.html'));
        } else {
            return file_get_contents(base_path('frontend/index.html'));
        }
    } else {
        return redirect(Utils::getEmbeddedAppUrl($request->query("host", null)) . "/" . $request->path());
    }
})->middleware('shopify.installed');

Route::get('/api/auth', function (Request $request) {
    $shop = Utils::sanitizeShopDomain($request->query('shop'));

    // Delete any previously created OAuth sessions that were not completed (don't have an access token)
    Session::where('shop', $shop)->where('access_token', null)->delete();

    return AuthRedirection::redirect($request);
});

Route::get('/api/auth/callback', function (Request $request) {
    $session = OAuth::callback(
        $request->cookie(),
        $request->query(),
        ['App\Lib\CookieHandler', 'saveShopifyCookie'],
    );

    $host = $request->query('host');
    $shop = Utils::sanitizeShopDomain($request->query('shop'));

    $response = Registry::register('/api/webhooks', Topics::APP_UNINSTALLED, $shop, $session->getAccessToken());
    if ($response->isSuccess()) {
        Log::debug("Registered APP_UNINSTALLED webhook for shop $shop");
    } else {
        Log::error(
            "Failed to register APP_UNINSTALLED webhook for shop $shop with response body: " .
                print_r($response->getBody(), true)
        );
    }

    //order create webhook
    $response = Registry::register('/api/webhooks', Topics::ORDERS_CREATE, $shop, $session->getAccessToken());
    if ($response->isSuccess()) {
        Log::debug("Registered ORDERS_CREATE webhook for shop $shop");
    } else {
        Log::error(
            "Failed to register ORDERS_CREATE webhook for shop $shop with response body: " .
            print_r($response->getBody(), true)
        );
    }

    $redirectUrl = Utils::getEmbeddedAppUrl($host);
    if (Config::get('shopify.billing.required')) {
        list($hasPayment, $confirmationUrl) = EnsureBilling::check($session, Config::get('shopify.billing'));

        if (!$hasPayment) {
            $redirectUrl = $confirmationUrl;
        }
    }

    return redirect($redirectUrl);
});

Route::get('/api/products/count', function (Request $request) {
    /** @var AuthSession */
    $session = $request->get('shopifySession'); // Provided by the shopify.auth middleware, guaranteed to be active

    $client = new Rest($session->getShop(), $session->getAccessToken());
    $result = $client->get('products/count');

    return response($result->getDecodedBody());
})->middleware('shopify.auth');

Route::post('/api/products', function (Request $request) {
    /** @var AuthSession */
    $session = $request->get('shopifySession'); // Provided by the shopify.auth middleware, guaranteed to be active

    $success = $code = $error = null;
    try {
        ProductCreator::call($session, 5);
        $success = true;
        $code = 200;
        $error = null;
    } catch (\Exception $e) {
        $success = false;

        if ($e instanceof ShopifyProductCreatorException) {
            $code = $e->response->getStatusCode();
            $error = $e->response->getDecodedBody();
            if (array_key_exists("errors", $error)) {
                $error = $error["errors"];
            }
        } else {
            $code = 500;
            $error = $e->getMessage();
        }

        Log::error("Failed to create products: $error");
    } finally {
        return response()->json(["success" => $success, "error" => $error], $code);
    }
})->middleware('shopify.auth');

Route::post('/api/webhooks', function (Request $request) {
    try {
        $OrderLogsModel = new \App\Models\OrderLogsModel();
        $topic = $request->header(HttpHeaders::X_SHOPIFY_TOPIC, '');
        $shop = $request->header(HttpHeaders::X_SHOPIFY_DOMAIN, '');
        //$topic = 'orders/create';
        //$shop = 'hn-checkout-extention-demo-1.myshopify.com';

        /*$response = Registry::process($request->header(), $request->getContent());
        if (!$response->isSuccess()) {
            Log::error("Failed to process '$topic' webhook: {$response->getErrorMessage()}");
            return response()->json(['message' => "Failed to process '$topic' webhook"], 500);
        }*/

        $myfile = fopen("order_create_".time().".txt", "w") or die("Unable to open file!");
        fwrite($myfile, $request->getContent());
        fclose($myfile);

        if($topic=='orders/create'){
            $shop_db_data = Session::where('shop', $shop)->get()->toArray();

            $order_json = $request->getContent();
            //$order_json = '{"id":5588118634719,"admin_graphql_api_id":"gid:\/\/shopify\/Order\/5588118634719","app_id":1354745,"browser_ip":"14.97.78.130","buyer_accepts_marketing":false,"cancel_reason":null,"cancelled_at":null,"cart_token":null,"checkout_id":35532617285855,"checkout_token":"2a63142854d1e8b045014f339b493c67","client_details":{"accept_language":null,"browser_height":null,"browser_ip":"14.97.78.130","browser_width":null,"session_hash":null,"user_agent":"Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/124.0.0.0 Safari\/537.36"},"closed_at":null,"company":null,"confirmation_number":"6TXVPA0H7","confirmed":true,"contact_email":"amit.webinopoly@gmail.com","created_at":"2024-04-29T10:12:31-04:00","currency":"USD","current_subtotal_price":"1475.90","current_subtotal_price_set":{"shop_money":{"amount":"1475.90","currency_code":"USD"},"presentment_money":{"amount":"1475.90","currency_code":"USD"}},"current_total_additional_fees_set":null,"current_total_discounts":"10.00","current_total_discounts_set":{"shop_money":{"amount":"10.00","currency_code":"USD"},"presentment_money":{"amount":"10.00","currency_code":"USD"}},"current_total_duties_set":null,"current_total_price":"1495.90","current_total_price_set":{"shop_money":{"amount":"1495.90","currency_code":"USD"},"presentment_money":{"amount":"1495.90","currency_code":"USD"}},"current_total_tax":"0.00","current_total_tax_set":{"shop_money":{"amount":"0.00","currency_code":"USD"},"presentment_money":{"amount":"0.00","currency_code":"USD"}},"customer_locale":"en-US","device_id":null,"discount_codes":[{"code":"reward_a2f485d2g14","amount":"10.00","type":"fixed_amount"}],"email":"amit.webinopoly@gmail.com","estimated_taxes":false,"financial_status":"paid","fulfillment_status":null,"landing_site":null,"landing_site_ref":null,"location_id":null,"merchant_of_record_app_id":null,"name":"#1002","note":null,"note_attributes":[],"number":2,"order_number":1002,"order_status_url":"https:\/\/hn-checkout-extention-demo-1.myshopify.com\/67010101471\/orders\/841e8a193e00e8849d2f0b851ad72824\/authenticate?key=0c94a28a3abf736b3e6273382a55cf54\u0026none=VQxWAlRFXQ","original_total_additional_fees_set":null,"original_total_duties_set":null,"payment_gateway_names":["manual"],"phone":null,"po_number":null,"presentment_currency":"USD","processed_at":"2024-04-29T10:12:30-04:00","reference":"1085f3d99ea389db090002e319408dde","referring_site":null,"source_identifier":"1085f3d99ea389db090002e319408dde","source_name":"shopify_draft_order","source_url":null,"subtotal_price":"1475.90","subtotal_price_set":{"shop_money":{"amount":"1475.90","currency_code":"USD"},"presentment_money":{"amount":"1475.90","currency_code":"USD"}},"tags":"","tax_exempt":false,"tax_lines":[],"taxes_included":false,"test":false,"token":"841e8a193e00e8849d2f0b851ad72824","total_discounts":"10.00","total_discounts_set":{"shop_money":{"amount":"10.00","currency_code":"USD"},"presentment_money":{"amount":"10.00","currency_code":"USD"}},"total_line_items_price":"1485.90","total_line_items_price_set":{"shop_money":{"amount":"1485.90","currency_code":"USD"},"presentment_money":{"amount":"1485.90","currency_code":"USD"}},"total_outstanding":"0.00","total_price":"1495.90","total_price_set":{"shop_money":{"amount":"1495.90","currency_code":"USD"},"presentment_money":{"amount":"1495.90","currency_code":"USD"}},"total_shipping_price_set":{"shop_money":{"amount":"20.00","currency_code":"USD"},"presentment_money":{"amount":"20.00","currency_code":"USD"}},"total_tax":"0.00","total_tax_set":{"shop_money":{"amount":"0.00","currency_code":"USD"},"presentment_money":{"amount":"0.00","currency_code":"USD"}},"total_tip_received":"0.00","total_weight":4535,"updated_at":"2024-04-29T10:12:33-04:00","user_id":87990173919,"billing_address":{"first_name":"Amit","address1":"123","phone":null,"city":"Houston","zip":"77074","province":"Texas","country":"United States","last_name":"Gmail","address2":null,"company":null,"latitude":null,"longitude":null,"name":"Amit Gmail","country_code":"US","province_code":"TX"},"customer":{"id":7276318228703,"email":"amit.webinopoly@gmail.com","created_at":"2024-04-29T09:35:00-04:00","updated_at":"2024-04-29T10:12:32-04:00","first_name":"Amit","last_name":"Gmail","state":"disabled","note":null,"verified_email":true,"multipass_identifier":null,"tax_exempt":false,"phone":null,"email_marketing_consent":{"state":"not_subscribed","opt_in_level":"single_opt_in","consent_updated_at":null},"sms_marketing_consent":null,"tags":"","currency":"USD","accepts_marketing":false,"accepts_marketing_updated_at":null,"marketing_opt_in_level":"single_opt_in","tax_exemptions":[],"admin_graphql_api_id":"gid:\/\/shopify\/Customer\/7276318228703","default_address":{"id":8813047939295,"customer_id":7276318228703,"first_name":"Amit","last_name":"Gmail","company":null,"address1":"123","address2":null,"city":"Houston","province":"Texas","country":"United States","zip":"77074","phone":null,"name":"Amit Gmail","province_code":"TX","country_code":"US","country_name":"United States","default":true}},"discount_applications":[{"target_type":"line_item","type":"discount_code","value":"10.0","value_type":"fixed_amount","allocation_method":"across","target_selection":"all","code":"reward_a2f485d2g14"}],"fulfillments":[],"line_items":[{"id":13878895182047,"admin_graphql_api_id":"gid:\/\/shopify\/LineItem\/13878895182047","attributed_staffs":[],"fulfillable_quantity":1,"fulfillment_service":"manual","fulfillment_status":null,"gift_card":false,"grams":0,"name":"The Compare at Price Snowboard","price":"785.95","price_set":{"shop_money":{"amount":"785.95","currency_code":"USD"},"presentment_money":{"amount":"785.95","currency_code":"USD"}},"product_exists":true,"product_id":8125800677599,"properties":[],"quantity":1,"requires_shipping":true,"sku":"","taxable":true,"title":"The Compare at Price Snowboard","total_discount":"0.00","total_discount_set":{"shop_money":{"amount":"0.00","currency_code":"USD"},"presentment_money":{"amount":"0.00","currency_code":"USD"}},"variant_id":44408614912223,"variant_inventory_management":"shopify","variant_title":null,"vendor":"HN Checkout Extention Demo 1","tax_lines":[],"duties":[],"discount_allocations":[{"amount":"5.29","amount_set":{"shop_money":{"amount":"5.29","currency_code":"USD"},"presentment_money":{"amount":"5.29","currency_code":"USD"}},"discount_application_index":0}]},{"id":13878895214815,"admin_graphql_api_id":"gid:\/\/shopify\/LineItem\/13878895214815","attributed_staffs":[],"fulfillable_quantity":1,"fulfillment_service":"manual","fulfillment_status":null,"gift_card":false,"grams":4536,"name":"The Complete Snowboard - Ice","price":"699.95","price_set":{"shop_money":{"amount":"699.95","currency_code":"USD"},"presentment_money":{"amount":"699.95","currency_code":"USD"}},"product_exists":true,"product_id":8125800448223,"properties":[],"quantity":1,"requires_shipping":true,"sku":"","taxable":true,"title":"The Complete Snowboard","total_discount":"0.00","total_discount_set":{"shop_money":{"amount":"0.00","currency_code":"USD"},"presentment_money":{"amount":"0.00","currency_code":"USD"}},"variant_id":44408614519007,"variant_inventory_management":"shopify","variant_title":"Ice","vendor":"Snowboard Vendor","tax_lines":[],"duties":[],"discount_allocations":[{"amount":"4.71","amount_set":{"shop_money":{"amount":"4.71","currency_code":"USD"},"presentment_money":{"amount":"4.71","currency_code":"USD"}},"discount_application_index":0}]}],"payment_terms":null,"refunds":[],"shipping_address":{"first_name":"Amit","address1":"123","phone":null,"city":"Houston","zip":"77074","province":"Texas","country":"United States","last_name":"Gmail","address2":null,"company":null,"latitude":29.6917896,"longitude":-95.514493,"name":"Amit Gmail","country_code":"US","province_code":"TX"},"shipping_lines":[{"id":4560867524831,"carrier_identifier":"4084464f694a06e6683c491d00cef27f","code":"custom","discounted_price":"20.00","discounted_price_set":{"shop_money":{"amount":"20.00","currency_code":"USD"},"presentment_money":{"amount":"20.00","currency_code":"USD"}},"phone":null,"price":"20.00","price_set":{"shop_money":{"amount":"20.00","currency_code":"USD"},"presentment_money":{"amount":"20.00","currency_code":"USD"}},"requested_fulfillment_service_id":null,"source":"shopify","title":"Standard Shipping","tax_lines":[],"discount_allocations":[]}]}';
            $order_arr = json_decode($order_json,1);
            if(isset($order_arr['id']) ){

                $orderExist = $OrderLogsModel->select_by_order_id($order_arr['id']);
                if(empty($orderExist)){
                    $total_line_items_price = $order_arr['total_line_items_price'];
                    $gm_discount_amount = 0;
                    $gm_discount_code = '';
                    if(isset($order_arr['discount_codes']) && !empty($order_arr['discount_codes']) ){
                        foreach($order_arr['discount_codes'] as $dc){
                            $gm_discount_code = $dc['code'];

                            if (strpos($gm_discount_code, "GMREWARD_") !== false) {
                                $gm_discount_amount = $dc['amount'];
                            }
                        }
                    }

                    $insertArr = [
                        'shop' => $shop,
                        'order_id' => $order_arr['id'],
                        'email' => $order_arr['email'],
                        'total_line_items_price' => $order_arr['total_line_items_price'],
                        'gm_discount_amount' => $gm_discount_amount,
                        'gm_discount_code' => $gm_discount_code,
                        'gm_api_member_number' => '',
                        'gm_api_items_price_res' => '',
                        'gm_api_discount_price_res' => '',
                        'status' => '0',
                        'add_date' => date('d-m-Y h:i:s'),
                    ];
                    $OrderLogsModel->insert_order_logs($insertArr);

                }
            }
        }
    } catch (InvalidWebhookException $e) {
        Log::error("Got invalid webhook request for topic '$topic': {$e->getMessage()}");
        return response()->json(['message' => "Got invalid webhook request for topic '$topic'"], 401);
    } catch (\Exception $e) {
        Log::error("Got an exception when handling '$topic' webhook: {$e->getMessage()}");
        return response()->json(['message' => "Got an exception when handling '$topic' webhook"], 500);
    }
});
