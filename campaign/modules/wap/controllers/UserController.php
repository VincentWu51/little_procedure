<?php
namespace campaign\modules\wap\controllers;

use Yii;
use campaign\models\User;
use campaign\components\Code;
use campaign\models\Login;

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
        $vcode = Yii::$app->request->post('vcode');
        $phone = Yii::$app->request->post('phone');
        $password = Yii::$app->request->post('password');
        
        if(!$password || !$phone || !$vcode){
            return Code::errorExit(Code::ERROR_PARAM_PARTIAL);
        }
        
        if(Yii::$app->session[Login::PHONE_CODE_SESSION] != $vcode){
            return Code::errorExit(Code::ERROR_VERIFY_CHECK);
        }
        
        $model_user = User::findOne(['phone' => $phone]);
        if ($model_user){
            return Code::errorExit(Code::ERROR_USER_PHONE_EXISTS);
        }
        
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
        }
        
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
        
        if(!$passwd || !$phone || !$vcode){
            return Code::errorExit(Code::ERROR_PARAM_PARTIAL);
        }
        
        $model_user = User::findOne(['phone' => $phone]);
        if(is_null($model_user)){
            return Code::errorExit(Code::ERROR_USER_PHONE_NOT_EXISTS);
        }
        
        $model_user->passwd = md5($passwd);
        $model_user->save();
        
        return Code::errorExit(Code::SUCC);
    }
    /**
    * @date: 2017年3月1日 下午5:09:57
    * @author: louzhiqiang
    * @return:
    * @desc:   需要验证码
    */
    public function actionPhoneSend(){
        $vcode = Yii::$app->request->post('vcode');
        if($vcode != Yii::$app->session[Login::VERIFY_CODE_SESSION_KEY]){
            return Code::errorExit(Code::ERROR_VERIFY_CHECK);
        }
        
        $phone = Yii::$app->request->post('phone');
        
        //发送手机验证码
        
        $code = '123456';
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
        
        if(!$userName || !$password){
            return Code::errorExit(Code::ERROR_PARAM_PARTIAL);
        }
        
        $model_user = User::findOne(['name'=>$userName]);
        if(is_null($model_user)){
            return Code::errorExit(Code::ERROR_USER_PHONE_NOT_EXISTS);
        }
        
        if(md5($password) != $model_user['passwd']){
            return Code::errorExit(Code::ERROR_USER_LOGIN);
        }
        
        Yii::$app->session[User::USER_LOGIN_STATUS_KEY] = true;
        
        return Code::errorExit(Code::SUCC);
    }
    public function afterAction($action, $result){
        exit($result);
    }
}
