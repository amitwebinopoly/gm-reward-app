<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Config;

class InexController extends Controller {

	public function __construct()
	{
		//$this->middleware('auth');
		//parent::login_user_details();
	}

    function INEX_random_string($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function INEX_random_string_with_chars($length = 10,$characters='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function base64_to_image($base64_string, $output_file) {
        $ifp = fopen($output_file, "wb");
        $data = explode(',', $base64_string);
        fwrite($ifp, base64_decode($data[1]));
        fclose($ifp);
        return $output_file;
    }

    function pagination($reload='#',$page,$tpages,$adjacents,$id){
        $prevlabel = "&lsaquo;";
        $nextlabel = "&rsaquo;";

        //$out = '<div class="btn-group" id="'.$id.'">';
        $out = '<ul class="pagination" id="'.$id.'">';

        // previous
        if($page==1) {
            //$out.='<button class="btn ink-reaction btn-default-bright" disabled>'.$prevlabel.'</button>';
            $out.='<li class="prev disabled" aria-disabled="true"><a class="button" href="javascript:;"><i class="fa fa-long-arrow-left"></i></a></li>';
        } elseif($page==2) {
            //$out.='<button class="btn ink-reaction btn-default-bright" data-page="1" >'.$prevlabel.'</button>';
            $out.='<li class="prev"><a class="button" data-page="1" href="javascript:;"><i class="fa fa-long-arrow-left"></i></a></li>';
        } else {
            //$out.='<button class="btn ink-reaction btn-default-bright" data-page="'.($page-1).'">'.$prevlabel.'</button>';
            $out.='<li class="prev"><a class="button" data-page="'.($page-1).'" href="javascript:;"><i class="fa fa-long-arrow-left"></i></a></li>';
        }

        // first
        if($page>($adjacents+1)) {
            //$out.='<button class="btn ink-reaction btn-default-bright" data-page="1" data-current_page="1">1</button>';
            $out.='<li><a class="button" data-page="1" data-current_page="1" href="javascript:;">1</a></li>';
        }

        // interval
        if($page>($adjacents+2)) {
            //$out.='<span class="btn">...</span>';
            $out.='<li class="disabled"><a class="button" href="javascript:;">...</a></li>';
        }

        // pages
        $pmin = ($page>$adjacents) ? ($page-$adjacents) : 1;
        $pmax = ($page<($tpages-$adjacents)) ? ($page+$adjacents) : $tpages;
        for($i=$pmin; $i<=$pmax; $i++) {
            if($i==$page) {
                //$out.='<button class="btn ink-reaction btn-default-dark" disable data-current_page="'.$i.'">'.$i.'</button>';
                //$out.='<span class="btn btn-default-dark" data-current_page="'.$i.'">'.$i.'</span>';
                $out.='<li class="active"><a class="button" data-current_page="'.$i.'" href="javascript:;">'.$i.'</a></li>';
            }
            elseif($i==1) {
                //$out.='<button class="btn ink-reaction btn-default-bright" data-page="'.$i.'">'.$i.'</button>';
                $out.='<li><a class="button" data-page="'.$i.'" href="javascript:;">'.$i.'</a></li>';
            }
            else {
                //$out.='<button class="btn ink-reaction btn-default-bright" data-page="'.$i.'" >'.$i.'</button>';
                $out.='<li><a class="button" data-page="'.$i.'" href="javascript:;">'.$i.'</a></li>';
            }
        }

        // interval
        if($page<($tpages-$adjacents-1)) {
            //$out.='<span class="btn ">...</span>';
            $out.='<li class="disabled"><a class="button" href="javascript:;">...</a></li>';
        }

        // last
        if($page<($tpages-$adjacents)) {
            //$out.='<button class="btn ink-reaction btn-default-bright" data-page="'.$tpages.'">'.$tpages.'</button>';
            $out.='<li><a class="button" data-page="'.$tpages.'" href="javascript:;">'.$tpages.'</a></li>';
        }

        // next
        if($page<$tpages) {
            //$out.='<button class="btn ink-reaction btn-default-bright" data-page="'.($page+1).'">'.$nextlabel.'</button>';
            $out.='<li class="next"><a class="button" data-page="'.($page+1).'" href="javascript:;"><i class="fa fa-long-arrow-right"></i></a></li>';
            //$out.= "<a href=\"" . $reload . "&amp;page=" . ($page+1) . "\">" . $nextlabel . "</a>\n";
        }
        else {
            //$out.='<button class="btn ink-reaction btn-default-bright" disabled>'.$nextlabel.'</button>';
            $out.='<li class="next disabled" aria-disabled="true"><a class="button" href="javascript:;"><i class="fa fa-long-arrow-right"></i></a></li>';
            //$out.= "<span>" . $nextlabel . "</span>\n";
        }

        //$out.= '</div>';
        $out.= '</ul>';

        return $out;
    }

    function allow_special_character_in_keyword($keyword){
        $new_keyword = str_replace("'","\'",$keyword);
        return $new_keyword;
    }

    function remove_special_character_in_keyword($keyword){
        $new_keyword = str_replace("\'","'",$keyword);
        return $new_keyword;
    }

    function remove_special_character_from_string($string) {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
        $string = preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one
        return trim($string,'-'); // remove hyphens at start and end
    }

    function remove_downloaded_csv_from_folder(){
        $dir_path = public_path().Config::get('constant.DOWNLOAD_TABLE_LOCATION');
        $files = scandir($dir_path);

        if(isset($files) && !empty($files)){
            foreach($files as $file){
                if($file!='.' && $file!='..') {
                    unlink($dir_path.$file);
                }
            }
        }
    }

    function timeAgo($time_ago) {

        $cur_time 	= time();
        $time_elapsed 	= $cur_time - $time_ago;
        $seconds 	= $time_elapsed ;
        $minutes 	= round($time_elapsed / 60 );
        $hours 		= round($time_elapsed / 3600);
        $days 		= round($time_elapsed / 86400 );
        $weeks 		= round($time_elapsed / 604800);
        $months 	= round($time_elapsed / 2600640 );
        $years 		= round($time_elapsed / 31207680 );
        $return='';
// Seconds
        if($seconds <= 60){
            $return= "$seconds seconds ago";
        }
//Minutes
        else if($minutes <=60){
            if($minutes==1){
                $return= "one minute ago";
            }
            else{
                $return= "$minutes minutes ago";
            }
        }
//Hours
        else if($hours <=24){
            if($hours==1){
                $return= "an hour ago";
            }else{
                $return= "$hours hours ago";
            }
        }
//Days
        else if($days <= 7){
            if($days==1){
                $return= "yesterday";
            }else{
                $return= "$days days ago";
            }
        }
//Weeks
        else if($weeks <= 4.3){
            if($weeks==1){
                $return= "a week ago";
            }else{
                $return= "$weeks weeks ago";
            }
        }
//Months
        else if($months <=12){
            if($months==1){
                $return= "a month ago";
            }else{
                $return= "$months months ago";
            }
        }
//Years
        else{
            if($years==1){
                $return= "one year ago";
            }else{
                $return= "$years years ago";
            }
        }

        return $return;
    }

    function replace_middle_char_of_word_in_string($string,$sign) {
        $string_arr = explode(' ',$string);
        $new_string_arr = array();
        // Ex. I AM BEST
        foreach($string_arr as $single_word){
            $sl = strlen($single_word);
            if($sl==1){
                array_push($new_string_arr,$single_word);   // I - I
            }else if($sl==2){
                $tmp_word = substr($single_word,0,1).$sign; // AM - A*
                array_push($new_string_arr,$tmp_word);
            }else {
                $tmp_word = substr($single_word,0,1).str_repeat($sign,$sl-2).substr($single_word,$sl-1,1);
                array_push($new_string_arr,$tmp_word);  // BEST - B**T
            }
        }
        if(!empty($new_string_arr)){
            return implode(' ',$new_string_arr);
        }else{
            return '';
        }
    }

    function get_location_data_from_address($address,$city){
        $address = str_replace(' ','-',trim($address.' '.$city));
        $geocode=file_get_contents('https://maps.google.com/maps/api/geocode/json?address='.$address.'&sensor=false&key='.env('GOOGLE_MAP_API_KEY'));
        $output= json_decode($geocode,1);

        $res = [];

        $res['lat'] = '';
        $res['lng'] = '';
        $res['country_name'] = '';
        $res['country_code'] = '';
        $res['state_name'] = '';
        $res['state_code'] = '';
        $res['city_name'] = '';
        $res['city_code'] = '';
        if(isset($output['results'][0]) && !empty($output['results'][0])){
            if(isset($output['results'][0]['geometry']['location']['lat']) && $output['results'][0]['geometry']['location']['lng']){
                $res['lat'] = $output['results'][0]['geometry']['location']['lat'];
                $res['lng'] = $output['results'][0]['geometry']['location']['lng'];
            }

            if(isset($output['results'][0]['address_components'])){
                foreach($output['results'][0]['address_components'] as $s){
                    if(isset($s['types'][0]) && $s['types'][0]=='country'){
                        $res['country_name'] = $s['long_name'];
                        $res['country_code'] = $s['short_name'];
                    }else if(isset($s['types'][0]) && $s['types'][0]=='administrative_area_level_1'){
                        $res['state_name'] = $s['long_name'];
                        $res['state_code'] = $s['short_name'];
                    }else if(isset($s['types'][0]) && $s['types'][0]=='locality'){
                        $res['city_name'] = $s['long_name'];
                        $res['city_code'] = $s['short_name'];
                    }
                }
            }
        }
        return $res;
    }

    function get_client_ip() {
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    function distance($lat1, $lon1, $lat2, $lon2, $unit) {
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        }
        else {
            $theta = $lon1 - $lon2;
            $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;
            $unit = strtoupper($unit);

            if ($unit == "K") {
                return ($miles * 1.609344);
            } else if ($unit == "N") {
                return ($miles * 0.8684);
            } else {
                return $miles;
            }
        }
    }

    function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
        $sort_col = array();
        foreach ($arr as $key => $row) {
            $sort_col[$key] = $row[$col];
        }
        array_multisort($sort_col, $dir, $arr);
    }

}
