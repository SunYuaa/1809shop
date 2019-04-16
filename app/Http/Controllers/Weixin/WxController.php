<?php

namespace App\Http\Controllers\Weixin;

use DemeterChain\C;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Model\Weixin\WxUserModel;
use App\Model\Weixin\WxTextModel;
use App\Model\Weixin\WxImgModel;
use App\Model\Weixin\WxVoiceModel;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\Storage;

class WxController extends Controller
{
    //微信第一次连接测试
    public function valid()
    {
        echo $_GET['echostr'];
    }

    /**
     * 微信事件
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
        $event = $data->Event;          //事件类型
        $MsgType = $data->MsgType;      //素材类型

        //扫码关注自动回复消息
        if($event=='subscribe') {
            //根据openid判断用户是否存在
            $where = [
                'openid'=>$openid
            ];
            $local_user = WxUserModel::where($where)->first();
            if ($local_user) {   //之前关注过
                echo '<xml><ToUserName><![CDATA[' . $openid . ']]></ToUserName><FromUserName><![CDATA[' . $wx_id. ']]></FromUserName><CreateTime>' . time() . '</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[' . '你回来啦~  ' . $local_user['nickname'] . ']]></Content></xml>';
                $res = WxUserModel::where($where)->update(['sub_status'=>1]);
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

                echo '<xml><ToUserName><![CDATA[' . $openid . ']]></ToUserName><FromUserName><![CDATA[' . $wx_id. ']]></FromUserName><CreateTime>' . time() . '</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[' . '欢迎关注~  ' . $userInfo['nickname'] . ']]></Content></xml>';
            }
        }
        //用户取消关注
        if($event=='unsubscribe'){
            $where = [
                'openid'=>$openid
            ];
            $res = WxUserModel::where($where)->update(['sub_status'=>2]);
            if($res){
                echo '取消关注成功';
            }else{
                echo '取消关注失败';
            }
        }
        //处理文本内容素材
        if($MsgType=='text'){
            //天气回复
            if(strpos($data->Content,'+天气')){
                $city = explode('+',$data->Content)[0];
                $url = 'https://free-api.heweather.net/s6/weather/now?key=HE1904161042371857&location='.$city;
                $weather = json_decode(file_get_contents($url),true);
                if($weather['HeWeather6'][0]['status']=='ok'){              //检测城市名是否正确
                    $cond_txt = $weather['HeWeather6'][0]['now']['cond_txt'];   //天气状况描述
                    $fl = $weather['HeWeather6'][0]['now']['fl'];               //体感温度
                    $tmp = $weather['HeWeather6'][0]['now']['tmp'];             //摄氏度
                    $hum = $weather['HeWeather6'][0]['now']['hum'];             //相对湿度
                    $wind_dir = $weather['HeWeather6'][0]['now']['wind_dir'];   //风向
                    $wind_sc = $weather['HeWeather6'][0]['now']['wind_sc'];     //风向
                    $str = date('Y-m-d')."\n".'天气状况: '.$cond_txt."\n".'体感温度:'.$fl."\n".'摄氏度: '.$tmp."\n".'相对湿度: '.$hum."\n".'风向: '.$wind_dir."\n".'风力: '.$wind_sc;

                    $response_xml = '<xml>
                                      <ToUserName><![CDATA['.$openid.']]></ToUserName>
                                      <FromUserName><![CDATA['.$wx_id.']]></FromUserName>
                                      <CreateTime>'.time().'</CreateTime>
                                      <MsgType><![CDATA[text]]></MsgType>
                                      <Content><![CDATA['.$str.']]></Content>
                                    </xml>';
                    echo $response_xml;
                }else{
                    $response_xml = '<xml>
                                      <ToUserName><![CDATA['.$openid.']]></ToUserName>
                                      <FromUserName><![CDATA['.$wx_id.']]></FromUserName>
                                      <CreateTime>'.time().'</CreateTime>
                                      <MsgType><![CDATA[text]]></MsgType>
                                      <Content><![CDATA[城市输入错误]]></Content>
                                    </xml>';
                    echo $response_xml;
                }
            }

            //获取用户信息 存入数据库
            $textData = [
                'openid' => $data->FromUserName,
                'createTime' => $data->CreateTime,
                'content' => $data->Content
            ];
            $res = WxTextModel::insert($textData);
            if($res){
                echo '内容添加成功';
            }else{
                echo '内容添加失败';
            }
        }
        //处理图片素材
        if($MsgType=='image'){
            $media_id = $data->MediaId;

            //media_id Url
            $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$this->getAccessToken().'&media_id='.$media_id;
            $client = new Client();
            $response = $client->get(new Uri($url));

            $headers = $response->getHeaders();               //获取响应头信息
            $img_file = $headers['Content-disposition'][0];   //图片信息

            $file_name = rtrim(substr($img_file,-20),'"');
            $new_file_name = 'weixin/'.substr(md5(time().mt_rand(11111,99999)),10,8).'_'.$file_name;

            $info = Storage::put($new_file_name,$response->getBody());   //保存图片
            if($info){
                //获取用户信息
                $imgData = [
                    'openid' => $data->FromUserName,
                    'createTime' => $data->CreateTime,
                    'imageurl' => 'storage/app/'.$new_file_name
                ];
                $res = WxImgModel::insert($imgData);
                if($res){
                    echo '图片添加成功';
                }else{
                    echo '图片添加失败';
                }
            }

        }
        //处理语音素材
        if($MsgType=='voice'){
            $media_id = $data->MediaId;
            $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$this->getAccessToken().'&media_id='.$media_id;
            $amr_data = file_get_contents($url);
            $file_name = time().mt_rand(11111,99999).'.amr';
            $info = file_put_contents('weixin/voice/'.$file_name,$amr_data);

            if($info){
                //获取用户信息
                $amrData = [
                    'openid' => $data->FromUserName,
                    'createTime' => $data->CreateTime,
                    'voice' => 'public/weixin/voice/'.$file_name
                ];
                $res = WxVoiceModel::insert($amrData);
                if($res){
                    echo '语音保存成功';
                }else{
                    echo '语音保存失败';
                }
            }
        }

    }

    /**
     * 群发消息
     * @param $openid_arr
     * @param $content
     */
    public function sendMsg($openid_arr,$content)
    {
        $msg = [
            'touser' => $openid_arr,
            'msgtype' => 'text',
            'text' => [
                'content' => $content
            ]
        ];

        $data = json_encode($msg,JSON_UNESCAPED_UNICODE);   //处理中文编码
        $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token='.$this->getAccessToken();
        //发送数据
        $client = new Client();
        $response = $client->request('post',$url,[
            'body' => $data
        ]);
        return $response->getBody();

    }
    /**
     * 发送群发内容
     */
    public function send(){
        $openid_list = WxUserModel::where(['sub_status'=>1])->get()->toArray();
        $openid_arr = array_column($openid_list,'openid');
        print_r($openid_arr);
        $content = '测试测试over';
        $response = $this->sendMsg($openid_arr,$content);
        return $response;
    }

    /**
     * 自定义菜单
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getMenu()
    {
        //url
        $url = ' https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$this->getAccessToken();
        //菜单数据
        $menu_data = [
            'button'    => [
                [
                    "name" => "菜单",
                    "sub_button" => [
                        [
                            "type" => "view",
                            "name" => "百度一下",
                            "url" => "http://www.baidu.com/"
                        ],
                        [
                        "type" => "click",
                        "name" => "赞一下",
                        "key" => "menu_key001"
                        ]
                    ]
                ],
                [
                    "type" => "pic_sysphoto",
                    "name" => "拍照",
                    "key" => "rselfmenu_1_0",
                    "sub_button" => [ ]
                ],
                [
                    "name" => "发送位置",
                    "type" => "location_select",
                    "key" => "rselfmenu_2_0"
                ]
            ]
        ];

        $json_str = json_encode($menu_data,JSON_UNESCAPED_UNICODE);  //处理中文编码
        //发送请求
        $client = new Client();
        $response = $client->request('POST',$url,[      //发送 json字符串
            'body'  => $json_str
        ]);

        //处理响应
        $res_str = $response->getBody();
        $arr = json_decode($res_str,true);

        //判断错误信息
        if($arr['errcode']>0){
            echo '创建菜单失败';
        }else{
            echo '创建菜单成功';
        }

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
            echo 'cache';echo "\n";
        }else{
            echo 'Nocache';echo "\n";
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



