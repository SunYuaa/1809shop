<?php

namespace App\Http\Controllers\Weixin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Model\User\WxUserModel;

class WxController extends Controller
{
    //微信第一次连接测试
    public function valid()
    {
        echo $_GET['echostr'];
    }

    /**
     * 扫描二维码自动回复
     */
    public function event()
    {
        //接受服务器推送
        $content = file_get_contents("php://input");
        //写入日志
        $time = date("Y-m-d H:i:s");
        $str = $time . $content . "\n";
        file_put_contents("logs/wx_event.log", $str, FILE_APPEND);
        $data = simplexml_load_string($content);

//        echo "ToUserName:".$data->ToUserName;echo '</br>';      //公众号ID
//        echo "FromUserName:".$data->FromUserName;echo '</br>';  //用户OpenID
//        echo "CreateTime:".$data->CreateTime;echo '</br>';      //时间戳
//        echo "MsgType:".$data->MsgType;echo '</br>';            //消息类型
//        echo "Event:".$data->Event;echo '</br>';                //事件类型
//        echo "EventKey:".$data->EventKey;echo '</br>';

        $wx_id = $data->ToUserName;     //公众号id
        $openid = $data->FromUserName;  //用户OpenId
        $event = $data->Event;          //时间类型

        //扫码关注自动回复消息
        if($event=='subscribe') {
            //根据openid判断用户是否存在
            $where = [
                'openid'=>$openid
            ];
            $local_user = WxUserModel::where($where)->first();
            print_r($local_user);
            if ($local_user) {   //之前关注过
                echo '<xml><ToUserName><![CDATA[' . $openid . ']]></ToUserName><FromUserName><![CDATA[' . $wx_id. ']]></FromUserName><CreateTime>' . time() . '</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[' . '欢迎回来' . $local_user['nickname'] . ']]></Content></xml>';
            } else {             //首次关注
                //获取用户信息
                $userInfo = $this->getUserInfo($openid);

                //入库
                $u_info = [
                    'openid' => $userInfo['openid'],
                    'nickname' => $userInfo['nickname'],
                    'sex' => $userInfo['sex'],
                    'headimgurl' => $userInfo['headimgurl'],
                    'subscribe_time' => $userInfo['subscribe_time'],
                ];
                $id = WxUserModel::insertGetId($u_info);

                echo '<xml><ToUserName><![CDATA[' . $openid . ']]></ToUserName><FromUserName><![CDATA[' . $wx_id. ']]></FromUserName><CreateTime>' . time() . '</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[' . '欢迎关注' . $userInfo['nickname'] . ']]></Content></xml>';
            }
        }
    }

    public function getMenu()
    {
        //url
        $url = ' https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$this->getAccessToken();
        //菜单数据
        $menu_data = [
            'button'    => [
                [
                    'type'  => 'click',
                    'name'  => '歌曲',
                    'key'   => 'key_menu_001'
                ],
                [
                    'name'  => '点击',
                    'sub_button'  => [
                        [
                            'type'  => 'click',
                            'name'  => '天气',
                            'key'   => 'key_menu_002'
                        ],
                        [
                            "type" => "click",
                            "name" => "赞一下我们",
                            "key"  => "key_menu_003"
                        ],
                    ]
                ],
            ]
        ];

        $json_str = json_encode($menu_data,JSON_UNESCAPED_UNICODE);  //处理中文编码
        //发送请求
        $client = new Clinet();


    }
    /**
     * 获取微信accessToken
     * @return mixed
     */
    public function getAccessToken()
    {
        $key = 'wx_access_token';
        $token = Redis::get($key);
        if($token){
            echo 'cache';
//            return $token;
        }else{
            echo 'Nocache';
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.env('WX_APPID').'&secret='.env('WX_SECRET').'';
            $response = file_get_contents($url);
            $arr = json_decode($response,true);

            //redis缓存
            Redis::set($key,$arr['access_token']);
            Redis::expire($key,3600);
            $token = $arr['access_token'];
        }
        return $token;
    }

    /**
     * 获取微信用户信息
     * @param $openid
     * @return mixed
     */
    public function getUserInfo($openid)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$this->getAccessToken().'&openid='.$openid.'&lang=zh_CN';
        $data = file_get_contents($url);
        $u = json_decode($data,true);
        return $u;
    }
}



