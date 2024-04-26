<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Config;

class GMController extends Controller {

    public $baseUrl="https://apimtst.gm.com/api-0549/gmrewards_services";
	public function __construct()
	{
		//$this->middleware('auth');
		//parent::login_user_details();
	}

    public function get_member_id_from_email($email){
        $curl = curl_init();

        $postData = [
            'body' => [
                "SourceCode" => env('GM_API_SOURCE_CODE'),
                "APIKey" => env('GM_API_KEY'),
                "EmailAddress" => $email
            ]
        ];

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseUrl.'/GMR2_MemberLookUp/Query',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postData,1),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic '.base64_encode(env('GM_API_USERNAME').':'.env('GM_API_PASSWORD'))
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response,1);
    }
    public function get_member_data_from_member_id($member_num){
        $curl = curl_init();

        $postData = [
            'body' => [
                "SourceCode" => env('GM_API_SOURCE_CODE'),
                "APIKey" => env('GM_API_KEY'),
                "Redemption Type" => 'NEWVEH',
                "Transaction Date" => date('m/d/Y h:i:s A'),
                "Member Number" => $member_num
            ]
        ];

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseUrl.'/GMR2_GetRedemptionInformation/GetRedemptionInformation',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postData,1),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic '.base64_encode(env('GM_API_USERNAME').':'.env('GM_API_PASSWORD'))
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response,1);
    }
    public function adjust_reward_amount($member_num,$credit_or_debit,$dollar_amount){
        $curl = curl_init();

        $postData = [
            'body' => [
                "SourceCode" => env('GM_API_SOURCE_CODE'),
                "ApiKey" => env('GM_API_KEY'),
                "MemberNumber" => $member_num,
                "TransactionDate" => date('m/d/Y'),
                "CreditOrDebit" => $credit_or_debit,//C or D
                "TransactionCode" => "MERCH",
                "TransactionAmount" => $dollar_amount,
                "CurrencyCode" => "USD"
            ]
        ];

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->baseUrl.'/GMRewardsEarnTxnWS/CreateEarnRewards2',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postData,1),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic '.base64_encode(env('GM_API_USERNAME').':'.env('GM_API_PASSWORD'))
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response,1);
    }

}
