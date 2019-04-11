<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Model\User\IndexUserModel;

class UserController extends Controller
{
    //user
    public function test(){
        $data  = IndexUserModel::first()->toArray();
        print_r($data);die;
        $key = 'tmp:aaa';
        $val = 'aaaaaaa';
        $rs = Redis::set($key,$val);		//设置键值
        $v = Redis::get($key);
        dump($v);
    }
}
