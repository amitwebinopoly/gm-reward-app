<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Middleware\EnsureFrontendShopAuth;

use App\Http\Controllers\AppController;
use App\Http\Controllers\FrontEndController;
use App\Http\Controllers\CronController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/', function () {
    return "Hello API";
});

//App routes
Route::post('/fetch_point_conversion_rate',[ AppController::class,'fetch_point_conversion_rate'])->middleware('shopify.auth');
Route::post('/post_point_conversion_rate',[ AppController::class,'post_point_conversion_rate'])->middleware('shopify.auth');

//frontend APIs
Route::middleware([EnsureFrontendShopAuth::class])->group(function () {
    Route::post('/get_cust_point',[ FrontEndController::class,'get_cust_point']);
    Route::post('/create_discount_code',[ FrontEndController::class,'create_discount_code']);
    Route::post('/adjust_amount',[ FrontEndController::class,'adjust_amount']);
});

//Cronjobs
Route::group(['prefix' => 'cron'], function(){
    Route::get('process_order', [CronController::class,'process_order'])->name('process_order');
});