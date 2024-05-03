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
        //$shop = 'gm-company-store.myshopify.com';

        /*$response = Registry::process($request->header(), $request->getContent());
        if (!$response->isSuccess()) {
            Log::error("Failed to process '$topic' webhook: {$response->getErrorMessage()}");
            return response()->json(['message' => "Failed to process '$topic' webhook"], 500);
        }*/

        /*$myfile = fopen("order_create_".time().".txt", "w") or die("Unable to open file!");
        fwrite($myfile, $request->getContent());
        fclose($myfile);*/

        if($topic=='orders/create'){
            //$shop_db_data = Session::where('shop', $shop)->get()->toArray();

            $order_json = $request->getContent();
            //$order_json = '{"id":5260174885049,"admin_graphql_api_id":"gid:\/\/shopify\/Order\/5260174885049","app_id":580111,"browser_ip":"68.13.47.91","buyer_accepts_marketing":true,"cancel_reason":null,"cancelled_at":null,"cart_token":"Z2NwLXVzLWNlbnRyYWwxOjAxSFdYWlpDQ0dSSDZSVkFSV0dQRlRCSlFX","checkout_id":29133253345465,"checkout_token":"e5ead0b7e6b643c3b0bdf17dfbdbbe38","client_details":{"accept_language":"en","browser_height":null,"browser_ip":"68.13.47.91","browser_width":null,"session_hash":null,"user_agent":"Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/124.0.0.0 Safari\/537.36"},"closed_at":null,"company":null,"confirmation_number":"KY688ZV5F","confirmed":true,"contact_email":"suebfredricks@cox.net","created_at":"2024-05-02T21:15:15-04:00","currency":"USD","current_subtotal_price":"22.95","current_subtotal_price_set":{"shop_money":{"amount":"22.95","currency_code":"USD"},"presentment_money":{"amount":"22.95","currency_code":"USD"}},"current_total_additional_fees_set":null,"current_total_discounts":"4.05","current_total_discounts_set":{"shop_money":{"amount":"4.05","currency_code":"USD"},"presentment_money":{"amount":"4.05","currency_code":"USD"}},"current_total_duties_set":null,"current_total_price":"32.57","current_total_price_set":{"shop_money":{"amount":"32.57","currency_code":"USD"},"presentment_money":{"amount":"32.57","currency_code":"USD"}},"current_total_tax":"2.12","current_total_tax_set":{"shop_money":{"amount":"2.12","currency_code":"USD"},"presentment_money":{"amount":"2.12","currency_code":"USD"}},"customer_locale":"en-US","device_id":null,"discount_codes":[{"code":"MERCH15","amount":"4.05","type":"percentage"}],"email":"suebfredricks@cox.net","estimated_taxes":false,"financial_status":"paid","fulfillment_status":null,"landing_site":"\/collections\/apparel","landing_site_ref":null,"location_id":null,"merchant_of_record_app_id":null,"name":"#79618","note":null,"note_attributes":[],"number":78618,"order_number":79618,"order_status_url":"https:\/\/www.gmcompanystore.com\/15608341\/orders\/b629e2f648f5d368b6da81de4046d342\/authenticate?key=3a61a0e4fbf7cffafea6563c245600bc\u0026none=VQVQAFVEVV9WT1NC","original_total_additional_fees_set":null,"original_total_duties_set":null,"payment_gateway_names":["shopify_payments"],"phone":null,"po_number":null,"presentment_currency":"USD","processed_at":"2024-05-02T21:15:10-04:00","reference":"7cccc24e7d5a90f9960a45be35943a9e","referring_site":"https:\/\/www.google.com\/","source_identifier":"7cccc24e7d5a90f9960a45be35943a9e","source_name":"web","source_url":null,"subtotal_price":"22.95","subtotal_price_set":{"shop_money":{"amount":"22.95","currency_code":"USD"},"presentment_money":{"amount":"22.95","currency_code":"USD"}},"tags":"","tax_exempt":false,"tax_lines":[{"price":"0.45","rate":0.015,"title":"Omaha City Tax","price_set":{"shop_money":{"amount":"0.45","currency_code":"USD"},"presentment_money":{"amount":"0.45","currency_code":"USD"}},"channel_liable":false},{"price":"1.67","rate":0.055,"title":"Nebraska State Tax","price_set":{"shop_money":{"amount":"1.67","currency_code":"USD"},"presentment_money":{"amount":"1.67","currency_code":"USD"}},"channel_liable":false}],"taxes_included":false,"test":false,"token":"b629e2f648f5d368b6da81de4046d342","total_discounts":"4.05","total_discounts_set":{"shop_money":{"amount":"4.05","currency_code":"USD"},"presentment_money":{"amount":"4.05","currency_code":"USD"}},"total_line_items_price":"27.00","total_line_items_price_set":{"shop_money":{"amount":"27.00","currency_code":"USD"},"presentment_money":{"amount":"27.00","currency_code":"USD"}},"total_outstanding":"0.00","total_price":"32.57","total_price_set":{"shop_money":{"amount":"32.57","currency_code":"USD"},"presentment_money":{"amount":"32.57","currency_code":"USD"}},"total_shipping_price_set":{"shop_money":{"amount":"7.50","currency_code":"USD"},"presentment_money":{"amount":"7.50","currency_code":"USD"}},"total_tax":"2.12","total_tax_set":{"shop_money":{"amount":"2.12","currency_code":"USD"},"presentment_money":{"amount":"2.12","currency_code":"USD"}},"total_tip_received":"0.00","total_weight":135,"updated_at":"2024-05-02T21:15:19-04:00","user_id":null,"billing_address":{"first_name":"Susan","address1":"5058 South 162nd Street","phone":"4027145874","city":"Omaha","zip":"68135","province":"Nebraska","country":"United States","last_name":"Fredricks","address2":null,"company":null,"latitude":41.2069929,"longitude":-96.16724029999999,"name":"Susan Fredricks","country_code":"US","province_code":"NE"},"customer":{"id":6907366047929,"email":"suebfredricks@cox.net","created_at":"2024-05-02T21:15:10-04:00","updated_at":"2024-05-02T21:15:21-04:00","first_name":"Susan","last_name":"Fredricks","state":"disabled","note":null,"verified_email":true,"multipass_identifier":null,"tax_exempt":false,"phone":null,"email_marketing_consent":{"state":"subscribed","opt_in_level":"single_opt_in","consent_updated_at":"2024-05-02T21:15:16-04:00"},"sms_marketing_consent":null,"tags":"","currency":"USD","tax_exemptions":[],"admin_graphql_api_id":"gid:\/\/shopify\/Customer\/6907366047929","default_address":{"id":8231869907129,"customer_id":6907366047929,"first_name":"Susan","last_name":"Fredricks","company":null,"address1":"5058 South 162nd Street","address2":null,"city":"Omaha","province":"Nebraska","country":"United States","zip":"68135","phone":"4027145874","name":"Susan Fredricks","province_code":"NE","country_code":"US","country_name":"United States","default":true}},"discount_applications":[{"target_type":"line_item","type":"discount_code","value":"15.0","value_type":"percentage","allocation_method":"across","target_selection":"all","code":"MERCH15"}],"fulfillments":[],"line_items":[{"id":12763763703993,"admin_graphql_api_id":"gid:\/\/shopify\/LineItem\/12763763703993","attributed_staffs":[],"current_quantity":3,"fulfillable_quantity":3,"fulfillment_service":"manual","fulfillment_status":null,"gift_card":false,"grams":45,"name":"Corvette C8 Magnetic Lapel Pin","price":"9.00","price_set":{"shop_money":{"amount":"9.00","currency_code":"USD"},"presentment_money":{"amount":"9.00","currency_code":"USD"}},"product_exists":true,"product_id":7110571622585,"properties":[],"quantity":3,"requires_shipping":true,"sku":"CSM-CR9287","taxable":true,"title":"Corvette C8 Magnetic Lapel Pin","total_discount":"0.00","total_discount_set":{"shop_money":{"amount":"0.00","currency_code":"USD"},"presentment_money":{"amount":"0.00","currency_code":"USD"}},"variant_id":41596525019321,"variant_inventory_management":"shopify","variant_title":null,"vendor":"Cruisin Sports","tax_lines":[{"channel_liable":false,"price":"0.34","price_set":{"shop_money":{"amount":"0.34","currency_code":"USD"},"presentment_money":{"amount":"0.34","currency_code":"USD"}},"rate":0.015,"title":"Omaha City Tax"},{"channel_liable":false,"price":"1.26","price_set":{"shop_money":{"amount":"1.26","currency_code":"USD"},"presentment_money":{"amount":"1.26","currency_code":"USD"}},"rate":0.055,"title":"Nebraska State Tax"}],"duties":[],"discount_allocations":[{"amount":"4.05","amount_set":{"shop_money":{"amount":"4.05","currency_code":"USD"},"presentment_money":{"amount":"4.05","currency_code":"USD"}},"discount_application_index":0}]}],"payment_terms":null,"refunds":[],"shipping_address":{"first_name":"Susan","address1":"5058 South 162nd Street","phone":"4027145874","city":"Omaha","zip":"68135","province":"Nebraska","country":"United States","last_name":"Fredricks","address2":null,"company":null,"latitude":41.2069929,"longitude":-96.16724029999999,"name":"Susan Fredricks","country_code":"US","province_code":"NE"},"shipping_lines":[{"id":4334709047481,"carrier_identifier":"650f1a14fa979ec5c74d063e968411d4","code":"Continental US","discounted_price":"7.50","discounted_price_set":{"shop_money":{"amount":"7.50","currency_code":"USD"},"presentment_money":{"amount":"7.50","currency_code":"USD"}},"is_removed":false,"phone":null,"price":"7.50","price_set":{"shop_money":{"amount":"7.50","currency_code":"USD"},"presentment_money":{"amount":"7.50","currency_code":"USD"}},"requested_fulfillment_service_id":null,"source":"shopify","title":"Continental US","tax_lines":[{"channel_liable":false,"price":"0.11","price_set":{"shop_money":{"amount":"0.11","currency_code":"USD"},"presentment_money":{"amount":"0.11","currency_code":"USD"}},"rate":0.015,"title":"Omaha City Tax"},{"channel_liable":false,"price":"0.41","price_set":{"shop_money":{"amount":"0.41","currency_code":"USD"},"presentment_money":{"amount":"0.41","currency_code":"USD"}},"rate":0.055,"title":"Nebraska State Tax"}],"discount_allocations":[]}]}';
            $order_arr = json_decode($order_json,1);
            if(isset($order_arr['id']) ){

                $orderExist = $OrderLogsModel->select_by_order_id($order_arr['id']);
                if(empty($orderExist)){
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
                        'gm_api_member_res' => '',
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
