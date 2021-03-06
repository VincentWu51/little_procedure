<?php
namespace campaign\modules\wap\controllers;

use Yii;
use campaign\models\User;
use campaign\components\Code;
use campaign\models\Login;
use campaign\components\Alisms;

class UserController extends BaseController{
    public $modelClass = '';
    
    const USER_WAP_REGISTER_STEP_ONE = "user_wap_register_step_one";
    
    const USER_WAP_REGISTER_STEP_TWO = "user_wap_register_step_two";
    
    public function beforeAction($action){
        parent::beforeAction($action);
        return true;
    }
    /**
    * @date: 2017年1月22日 下午5:09:09
    * @author: louzhiqiang
    * @return:
    * @desc:   注册添加
    */
    public function actionRegister(){
        $vcode = Yii::$app->request->post('phoneCode');
        $phone = Yii::$app->request->post('phone');
        $password = Yii::$app->request->post('passwd');
	    $reset = Yii::$app->request->post("reset", "");

	    Yii::info("----register----注册接口接受的参数：".json_encode($_POST, JSON_UNESCAPED_UNICODE), "user");
        
        if(!$password || !$phone || !$vcode){
            return Code::errorExit(Code::ERROR_PARAM_PARTIAL);
        }
        
        if(Yii::$app->session[Login::PHONE_CODE_SESSION] != $vcode){
            return Code::errorExit(Code::ERROR_VERIFY_CHECK);
        }
        Yii::info("-----验证码验证通过-------", 'user');
        
        $model_user = User::findOne(['phone' => $phone]);
        if ($model_user && !$reset){
            return Code::errorExit(Code::ERROR_USER_PHONE_EXISTS);
        }
        Yii::info("------用户手机号已经存在------", 'user');
        
        if(!$model_user){
            $model_user = new User();
            $model_user->id   = md5($phone);
            $model_user->name = $phone;
            $model_user->phone = $phone;
            $model_user->createTime = time();
            $model_user->photoUrl   = '';
            $model_user->userInfo = '';
            $model_user->source = User::USER_SOURCE_WAP;
            $model_user->passwd = $password;
            $model_user->save();
        }else{
	        $model_user->passwd = $password;
            $model_user->save();
	    }
        Yii::info("-----数据已经保存进去-----", 'user');
        return Code::errorExit(Code::SUCC);
    }
    /**
    * @date: 2017年3月1日 下午6:18:26
    * @author: louzhiqiang
    * @return:
    * @desc:   设置密码
    */
    public function actionPasswdSet(){
        $passwd = Yii::$app->request->post('passwd');
        $phone  = Yii::$app->request->post('phone');
        $vcode  = Yii::$app->request->post('vcode');

        Yii::info("-----actionPasswdSet---重置密码---参数：".json_encode($_POST, JSON_UNESCAPED_UNICODE), 'user');

        if(!$passwd || !$phone || !$vcode){
            return Code::errorExit(Code::ERROR_PARAM_PARTIAL);
        }
        
        $model_user = User::findOne(['phone' => $phone]);
        if(is_null($model_user)){
            return Code::errorExit(Code::ERROR_USER_PHONE_NOT_EXISTS);
        }

        Yii::info("-----actionPasswdSet---发现用户---", 'user');

        $model_user->passwd = ($passwd);
        $model_user->save();
        Yii::info("-----actionPasswdSet---保存密码成功-----", 'user');
        
        return Code::errorExit(Code::SUCC);
    }
    /**
    * @date: 2017年3月1日 下午5:09:57
    * @author: louzhiqiang
    * @return:
    * @desc:   需要验证码
    */
    public function actionPhoneSend(){
        $vcode = Yii::$app->request->post('verifyCode');
	    Yii::info("---发送验证码借口，图形验证码：".$vcode, "api");
        if($vcode != Yii::$app->session[Login::VERIFY_CODE_SESSION_KEY]){
            return Code::errorExit(Code::ERROR_VERIFY_CHECK);
        }
        
        $phone = Yii::$app->request->post('phone');
        
        //发送手机验证码
        $code = rand(100000, 999999);
	    $accesskeyid = "LTAIzb9NF6L2AYtJ";
	    $accesskeysecret = "SEkvsn6qXntLYfq4PeQ19gVRdbVdAW";
        $smstempcode = "SMS_50820106";
        $params_code = "{'code':'".$code."'}";
        $sms = new Alisms($accesskeyid, $accesskeysecret);
        $return_arr = $sms->smsend($phone, $smstempcode, $params_code);

	    Yii::info("----sms接口返回数据：".json_encode($return_arr, JSON_UNESCAPED_UNICODE), "api");
        Yii::$app->session[Login::PHONE_CODE_SESSION] = $code;
        Yii::$app->session[self::USER_WAP_REGISTER_STEP_ONE] = true;
        return Code::errorExit(Code::SUCC);
    }
    /**
    * @date: 2017年3月6日 上午10:25:40
    * @author: louzhiqiang
    * @return:
    * @desc:   登陆
    */
    public function actionLogin(){
        $password = trim(Yii::$app->request->post('passwd'));
        $userName = trim(Yii::$app->request->post('userName'));
        Yii::info("-----登录信息----用户名：".$userName."---密码：".$password, 'user');
        if(!$userName || !$password){
            return Code::errorExit(Code::ERROR_PARAM_PARTIAL);
        }
        
        $model_user = User::findOne(['name'=>$userName]);
        if(is_null($model_user)){
            return Code::errorExit(Code::ERROR_USER_PHONE_NOT_EXISTS);
        }
        
        if(($password) != $model_user['passwd']){
            return Code::errorExit(Code::ERROR_USER_LOGIN);
        }
        Yii::info("-------登录成功-----", 'user');
        Yii::$app->session[User::USER_LOGIN_STATUS_KEY] = $model_user['id'];
        
        return Code::errorExit(Code::SUCC);
    }
    /**
     * 获得登录状态
     */
    public function actionLoginStatus(){
        if( Yii::$app->session[User::USER_LOGIN_STATUS_KEY] ){
            return Code::errorExit(Code::SUCC);
        }
        return Code::errorExit(Code::ERROR_USER_NO_LOGIN);
    }
    //获得用户信息
    public function actionInfo(){
	if(!Yii::$app->session[User::USER_LOGIN_STATUS_KEY]){
		return Code::errorExit(Code::ERROR_USER_NO_LOGIN);
	}
	$userInfo = User::find()->where(['id' => Yii::$app->session[User::USER_LOGIN_STATUS_KEY]])->asArray()->one();
	
        return json_encode(array(
		'code' => Code::SUCC,
		'info' => Code::$arr_code_status[Code::SUCC],
		'data' => $userInfo,
	), JSON_UNESCAPED_UNICODE);
    }
    public function afterAction($action, $result){
        exit($result);
    }
}
