<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
//phpinfo
Route::get('/info', function () {
    phpinfo();
});


//user
Route::get('user/test','User\UserController@test'); //测试redis


//微信接口
Route::get('weixin/valid','Weixin\WxController@valid');    //第一次接口测试
Route::post('weixin/valid','Weixin\WxController@event');    //微信事件
Route::get('weixin/access_token','Weixin\WxController@getAccessToken');   //获取access_token
Route::get('weixin/send','Weixin\WxController@send');   //群发消息


//微信支付
Route::get('weixin/pay/test','Weixin\WxpayController@test');