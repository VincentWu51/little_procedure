<?php
namespace campaign\modules\api\controllers;

use campaign\components\XUtils;
use Yii;
use campaign\models\Order;
use campaign\models\Campaign;
use campaign\components\Code;
use campaign\models\Content;
use campaign\models\Evaluate;
use campaign\models\User;
use campaign\components\WechatPay;

class OrderController extends BaseController{
    public $modelClass = '';
    private $__perNum = 10;
    public function beforeAction($action){
        parent::beforeAction($action);
        $this->getLoginStatus();
        return true;
    }
    /**
    * @date: 2017年1月21日 下午4:41:36
    * @author: louzhiqiang
    * @return:
    * @desc:   订单添加
    */
    public function actionAdd(){
        $campId = trim(Yii::$app->request->post('campId'), "'");
        $userList = trim(Yii::$app->request->post('userList'), "'");
        $num = trim(Yii::$app->request->post('num'), "'");
        $userId = $this->userId;
        Yii::info("-----添加订单---参数：".print_r(Yii::$app->request->post(), true), 'api');
        $campInfo = Campaign::find()
                                ->where(['id' => $campId])
                                ->asArray()
                                ->one();
        if($campInfo['totalNum'] < $num){
            return Code::errorExit(Code::ERROR_ORDER_CAMPNUM);
        }
        $userInfo = User::findOne(['id'=>$userId]);
        
        $userList_arr = json_decode($userList, true);
        
        if( count($userList_arr) != $num ){
            return Code::errorExit(Code::ERROR_ORDER_NUM);
        }
        
        try{
            $model_order = new Order();
            $model_order->userId = $userId;
            $model_order->campId = $campId;
            $model_order->num = $num;
            $model_order->mark = $userList;
            $model_order->phone = $userList_arr[0]['phone'];
            $model_order->userName = $userInfo['name'];
            $model_order->amount = $campInfo['price'] * $num;
            $model_order->campTitle = $campInfo['title'];
            $model_order->createTime = time();
            $model_order->updateTime = time();
            $model_order->status = Order::STATUS_ORDER_PAY_SUCCESS;
            $result_insert = $model_order->save();
        }catch (\yii\db\IntegrityException $e){
            $message = $e->getMessage()."---".$e->getTraceAsString();
        }catch (\yii\base\UnknownPropertyException $e){
            $message = $e->getMessage()."---".$e->getTraceAsString();
        }catch (\yii\db\Exception $e){
            $message = $e->getMessage()."---".$e->getTraceAsString();
        }catch (\yii\base\ErrorException $e){
            $message = $e->getMessage()."---".$e->getTraceAsString();
        }
        
        if( !$result_insert && isset($message)){
            Yii::info("-----------添加订单失败------".$message, 'order');
            return Code::errorExit(Code::ERROR_ORDER_CREATE);
        }
        return Code::errorExit(Code::SUCC);
    }
    /**
    * @date: 2017年1月21日 下午4:41:23
    * @author: louzhiqiang
    * @return:
    * @desc:   订单列表
    */
    public function actionList(){
        $userId = $this->userId;
        $page   = Yii::$app->request->post('page', 1);
        
        $condition = ['userId' => $userId];
        
        $list = Order::find()
                        ->where($condition)
                        ->orderBy(['updateTime'=> SORT_DESC])
                        ->limit($this->__perNum)
                        ->offset(($page-1) * $this->__perNum)
                        ->asArray()
                        ->all();
        //处理数据
        foreach ($list as $key=>$order){
            $campInfo = Campaign::findOne(['id' => $order['campId']]);
            $headImg = Content::find()
                                ->where(['campId'=>$campInfo['id'], 'fieldName'=>Content::FIELD_HEAD_IMAGE])
                                ->asArray()
                                ->one();
            
            $list[$key]['campInfo'] = array(
                'headImg' => Content::getImagePath($headImg['content']),
            );
            $list[$key]['statusMark'] = Order::$arr_order_status[$list[$key]['status']];
            $list[$key]['mark'] = json_decode($order['mark'], true);
            
            $list[$key]['createTime'] = date("Y-m-d", $order['createTime']);
            
            if(Order::STATUS_ORDER_CAMP_OVER == $list[$key]['status']){
                 $list[$key]['evaluateMark'] = Order::$arr_order_evaluate[$order['evaluateStatus']];
            }
        }
        
        return json_encode(array(
            'code' => Code::SUCC,
            'info' => Code::$arr_code_status[Code::SUCC],
            'data' => $list,
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    /**
    * @date: 2017年2月8日 下午4:35:48
    * @author: louzhiqiang
    * @return:
    * @desc:   评价
    */
    public function actionEvaluate(){
        Yii::info("-----post过来的数据是：".print_r(Yii::$app->request->post(), true)."----get过来的数据是：".print_r(Yii::$app->request->get(), true), 'order');
        $starLevel = Yii::$app->request->post('starLevel');
        if(!$starLevel){
            return json_encode(array(
                'code' => Code::ERROR_ORDER_EVA_PARAM_PARTIAL,
                'info' => '请添加评分'
            ), JSON_UNESCAPED_UNICODE);
        }
        $orderId   = Yii::$app->request->post('orderId');
        $mark = Yii::$app->request->post('mark');
        if(!$mark){
            $mark = "默认好评";
        }
        $userId = $this->userId;
        
        $eva_model = new Evaluate();
        $eva_model->orderId = $orderId;
        $eva_model->starLevel = $starLevel;
        $eva_model->content   = $mark;
        $eva_model->userId    = $userId;
        $orderInfo = Order::find()
                               ->where(['id'=> $orderId])
                               ->asArray()
                               ->one();
        Yii::info("----插入评价时查到的订单信息：".print_r($orderInfo, true), 'order');
        $eva_model->campId = $orderInfo['campId'];
        
        $eva_model->save();
        
        $order_model = Order::findOne(['id' => $orderId]);
        $order_model->evaluateStatus = Order::EVALUATE_ORDER_DONE;
        $order_model->save();
        
        return Code::errorExit(Code::SUCC);
    }
    /**
     * 微信支付请求第三方支付
     */
    public function actionPay(){
        $orderId = Yii::$app->request->post('orderId');
        $orderId = '123';
        if(!$orderId){
            return Code::errorExit(Code::ERROR_PARAM_PARTIAL);
        }

        $orderInfo = Order::findOne(['id'=>$orderId]);

        $notify_url  = "http://www.ioutdoor.org/api/order/payCallback";

        $wechatPay = new WechatPay(Yii::$app->params['appId'], $this->userId, Yii::$app->params['mchId'], $notify_url, Yii::$app->params['orderKey']);
        $params['body'] = '商品描述'; //商品描述
        $params['out_trade_no'] = 'O20160617021323-001'; //自定义的订单号
        $params['total_fee'] = '1'; //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'JSAPI'; //交易类型 JSAPI | NATIVE | APP | WAP
        $result = $wechatPay->unifiedOrder( $params );

        var_dump($result);die;
        /*
        $params['attach']      = '付款测试';
        $params['body']        = "ioutdoor活动付款";
        $params['mch_id']      = Yii::$app->params['mchId'];
        $params['notify_url']  = "http://www.ioutdoor.org/api/order/payCallback";
        $params['nonce_str']   = XUtils::getURandom();
        $params['out_trade_no']  = $orderId;
        $params['spbill_create_ip']  = XUtils::get_client_ip();
        $params['total_fee']  = 1;
        $params['trade_type']  = 'JSAPI';
        $params['time_start']  = date("YmdHis");
        $params['time_expire']   = date("YmdHis", strtotime("+1 day"));
        $params['openid']      = $this->userId;
        Yii::info("-----order create to wx----param is ".print_r($params, true), 'order');
        $params['sign'] = strtoupper(md5(http_build_query($params)."&key=".Yii::$app->params['orderKey']));
        Yii::info("-----order create to wx----sign is ".$params['sign'], 'order');
        */


    }
    public function afterAction($action, $result){
        exit($result);
    }
}