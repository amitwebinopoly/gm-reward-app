<?php
namespace App\Http\Controllers;

class CurlRequest{
    public static $lastHttpCode;

    protected static function init($url, $httpHeaders = array()){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $headers = array();
        foreach ($httpHeaders as $key => $value) {
            $headers[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        return $ch;
    }
    public static function get($url, $httpHeaders = array()){
        $ch = self::init($url, $httpHeaders);
        return self::processRequest($ch);
    }
    public static function post($url, $data, $httpHeaders = array()){
        $ch = self::init($url, $httpHeaders);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        return self::processRequest($ch);
    }
    public static function put($url, $data, $httpHeaders = array()){
        $ch = self::init($url, $httpHeaders);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        return self::processRequest($ch);
    }
    public static function delete($url, $httpHeaders = array()){
        $ch = self::init($url, $httpHeaders);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        return self::processRequest($ch);
    }
    protected static function processRequest($ch){
        $output = curl_exec($ch);
        if (curl_errno($ch)) {
            //throw new Exception\CurlException(curl_errno($ch) . ' : ' . curl_error($ch));
        }
        self::$lastHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $output;
    }
}

class GraphqlController{

    static $headers = array();
    static $domain = '';
    static $url = '';
    static $_curl;

    public static $microtimeOfLastApiCall;
    public static $timeAllowedForEachApiCall = 0.5;

    function __construct($domain, $headers, $storefront = false) {
        if ($headers == '') {
            return false;
        }

        self::$domain = $domain;
        if (self::$domain != '' && !empty($headers) && is_array($headers)) {
            if($storefront==true){
                self::$url = 'https://' . self::$domain . '/api/2024-04/graphql.json';
            }else{
                self::$url = 'https://' . self::$domain . '/admin/api/2024-04/graphql.json';
            }
            self::$headers = $headers;
        }
        self::$_curl = new CurlRequest();
    }
    public function runByQuery($query_data) {
        $query = <<<"JSON"
$query_data
JSON;

        sleep(0.5);
        $qlres = $this->graphQlPost($query, true);
        try {
            if ($qlres != '') {
                $storeRes = json_decode($qlres, true);
                return $storeRes;
            }
        } catch (Exception $ex) {
            return array();
        }
    }
    public function runByMutation($mutation,$input) {
        $query = <<<"JSON"
$mutation
JSON;

        $variables = json_decode($input);
        $json = json_encode(['query' => $query, 'variables' => $variables]);
        sleep(0.5);
        $qlres = $this->graphQlPost($json);
        try {
            if ($qlres != '') {
                $storeRes = json_decode($qlres, true);
                return $storeRes;
            }
        } catch (Exception $ex) {
            return array('status' => 0);
        }
    }
    public static function checkApiCallLimit($firstCallWait = false) {
        $timeToWait = 0;
        if (static::$microtimeOfLastApiCall == null) {
            if ($firstCallWait) {
                $timeToWait = static::$timeAllowedForEachApiCall;
            }
        } else {
            $now = microtime(true);
            $timeSinceLastCall = $now - static::$microtimeOfLastApiCall;
            //Ensure 2 API calls per second
            if ($timeSinceLastCall < static::$timeAllowedForEachApiCall) {
                $timeToWait = static::$timeAllowedForEachApiCall - $timeSinceLastCall;
            }
        }

        if ($timeToWait) {
            //convert time to microseconds
            $microSecondsToWait = $timeToWait * 1000000;
            //Wait to maintain the API call difference of .5 seconds
            usleep($microSecondsToWait);
        }

        static::$microtimeOfLastApiCall = microtime(true);
    }
    private function graphQlPost($query, $search = false) {
        self::checkApiCallLimit();
        if ($search == true) {
            self::$headers['Content-type'] = 'application/graphql';
        } else {
            self::$headers['Content-type'] = 'application/json;charset=utf-8';
        }

        return self::$_curl->post(self::$url, $query, self::$headers);
    }

    public function create_shopify_checkout($postData){
        $CheckoutlineItems = [];
        if(isset($postData['cartItems']) && !empty($postData['cartItems'])){
            $items_arr = json_decode(html_entity_decode($_POST['cartItems'], ENT_QUOTES),1);
            foreach($items_arr as $item){
                $customAttributes = [];
                if(isset($item['properties']) && !empty($item['properties'])){
                    foreach($item['properties'] as $item_prop_key=>$item_prop_value){
                        $prop_arr = [];
                        $prop_arr['key'] = $item_prop_key;
                        $prop_arr['value'] = $item_prop_value;
                        array_push($customAttributes,$prop_arr);
                    }
                }

                $tmp_arr = [];
                $tmp_arr['quantity'] = intval($item['quantity']);
                $tmp_arr['variantId'] = base64_encode("gid://shopify/ProductVariant/".$item['id']);
                $tmp_arr['customAttributes'] = $customAttributes;
                array_push($CheckoutlineItems,$tmp_arr);
            }
        }


        $mutation = 'mutation checkoutCreate($input: CheckoutCreateInput!) {
          checkoutCreate(input: $input) {
            checkout { id }
            checkoutUserErrors { code field message }
          }
        }
        ';
        $variables['input'] = array(
            'email'=> $postData['shipping_email'],
            'lineItems' => $CheckoutlineItems,
            /*'presentmentCurrencyCode' => 'USD',*/
            'shippingAddress' => array(
                'address1' => $postData['shipping_address'],
                'address2' => $postData['shipping_address2'],
                'city' => $postData['shipping_city'],
                'province' => $postData['shipping_state'],
                'country' => $postData['shipping_country'],
                'firstName' => $postData['shipping_first_name'],
                'lastName' => $postData['shipping_last_name'],
                'zip' => $postData['shipping_pincode']
            )
        );
        $checkout_data = $this->runByMutation($mutation,json_encode($variables));
        return $checkout_data;
    }
    public function create_first_shopify_checkout($postData){
        $CheckoutlineItems = [];
        if(isset($postData['cartItems']) && !empty($postData['cartItems'])){
            $items_arr = json_decode(html_entity_decode($_POST['cartItems'], ENT_QUOTES),1);
            foreach($items_arr as $item){
                $customAttributes = [];
                if(isset($item['properties']) && !empty($item['properties'])){
                    foreach($item['properties'] as $item_prop_key=>$item_prop_value){
                        $prop_arr = [];
                        $prop_arr['key'] = $item_prop_key;
                        $prop_arr['value'] = $item_prop_value;
                        array_push($customAttributes,$prop_arr);
                    }
                }

                $tmp_arr = [];
                $tmp_arr['quantity'] = intval($item['quantity']);
                $tmp_arr['variantId'] = base64_encode("gid://shopify/ProductVariant/".$item['id']);
                $tmp_arr['customAttributes'] = $customAttributes;
                array_push($CheckoutlineItems,$tmp_arr);
            }
        }

        $mutation = 'mutation checkoutCreate($input: CheckoutCreateInput!) {
          checkoutCreate(input: $input) {
            checkout { id }
            checkoutUserErrors { code field message }
          }
        }
        ';
        $variables['input'] = array(
            'lineItems' => $CheckoutlineItems
        );
        if(isset($_POST['checkout_email']) && !empty($_POST['checkout_email'])){
            $variables['input']['email'] = $_POST['checkout_email'];
        }
        $checkout_data = $this->runByMutation($mutation,json_encode($variables));
        return $checkout_data;
    }
    public function apply_discount_code_checkout($checkout_id,$discount_code){
        $mutation = 'mutation checkoutDiscountCodeApplyV2($discountCode: String!, $checkoutId: ID!) {
          checkoutDiscountCodeApplyV2(discountCode: $discountCode, checkoutId: $checkoutId) {
            checkout { id }
            checkoutUserErrors {
              code field message
            }
          }
        }';
        $variables = array(
            'checkoutId'=> $checkout_id,
            'discountCode' => $discount_code
        );
        $checkout_data = $this->runByMutation($mutation,json_encode($variables));
        return $checkout_data;
    }
    public function remove_discount_code_checkout($checkout_id){
        $mutation = 'mutation checkoutDiscountCodeRemove($checkoutId: ID!) {
          checkoutDiscountCodeRemove(checkoutId: $checkoutId) {
            checkout { id }
            checkoutUserErrors { code field message }
          }
        }';
        $variables = array(
            'checkoutId'=> $checkout_id
        );
        $checkout_data = $this->runByMutation($mutation,json_encode($variables));
        return $checkout_data;
    }
    public function update_checkout_email($checkout_id,$email){
        $mutation = 'mutation checkoutEmailUpdateV2($checkoutId: ID!, $email: String!) {
          checkoutEmailUpdateV2(checkoutId: $checkoutId, email: $email) {
            checkout { id }
            checkoutUserErrors { code field message }
          }
        }';
        $variables = array(
            'checkoutId'=> $checkout_id,
            'email' => $email
        );
        $checkout_data = $this->runByMutation($mutation,json_encode($variables));
        return $checkout_data;
    }
    public function update_checkout_shipping_address($postData,$checkout_id){
        $mutation = 'mutation checkoutShippingAddressUpdateV2($shippingAddress: MailingAddressInput!, $checkoutId: ID!) {
          checkoutShippingAddressUpdateV2(shippingAddress: $shippingAddress, checkoutId: $checkoutId) {
            checkout { id }
            checkoutUserErrors { code field message }
          }
        }';
        $variables = array(
            'checkoutId'=> $checkout_id,
            'shippingAddress' => array(
                'address1' => $postData['shipping_address'],
                'address2' => $postData['shipping_address2'],
                'city' => $postData['shipping_city'],
                'province' => $postData['shipping_state'],
                'country' => $postData['shipping_country'],
                'firstName' => $postData['shipping_first_name'],
                'lastName' => $postData['shipping_last_name'],
                'zip' => $postData['shipping_pincode']
            )
        );
        $checkout_data = $this->runByMutation($mutation,json_encode($variables));
        return $checkout_data;
    }
    public function update_checkout_shipping_line($shippingHandle,$checkout_id){
        $mutation = 'mutation checkoutShippingLineUpdate($checkoutId: ID!, $shippingRateHandle: String!) {
          checkoutShippingLineUpdate(checkoutId: $checkoutId, shippingRateHandle: $shippingRateHandle) {
            checkout { id }
            checkoutUserErrors { code field message }
          }
        }';
        $variables = array(
            'checkoutId'=> $checkout_id,
            'shippingRateHandle' => $shippingHandle
        );
        $checkout_data = $this->runByMutation($mutation,json_encode($variables));
        return $checkout_data;
    }
    public function get_shopify_checkout($checkout_id){
        $query = '{
          node(id:"'.$checkout_id.'"){
            ...on Checkout{
                id
                ready
                availableShippingRates{
                    shippingRates{
                        handle
                        title
                        priceV2{
                            amount
                        }
                    }
                }
                lineItems(first:50){
                    edges{
                        node{
                            id quantity title
                            variant{
                                id
                            }
                            discountAllocations{
                                allocatedAmount{
                                    amount
                                }
                                discountApplication{
                                    allocationMethod
                                    targetSelection
                                    targetType
                                    value{
                                        ...on PricingPercentageValue{
                                            percentage
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                discountApplications(first:10){
                    edges{
                        node{
                            allocationMethod
                            targetSelection
                            targetType
                            value{
                                ...on PricingPercentageValue{
                                    percentage
                                }
                            }
                        }
                    }
                }
                appliedGiftCards{
                    id lastCharacters
                    presentmentAmountUsed{ amount }
                }
            }
          }
        }';
        $checkouts = $this->runByQuery($query);
        return $checkouts;
    }

}
