<?php
namespace campaign\modules\api\controllers;

use Yii;
use campaign\components\Code;
use campaign\models\Campaign;
use campaign\models\Search;
use campaign\models\Content;
use campaign\components\XUtils;
use campaign\models\Evaluate;

class CampaignController extends BaseController{
    public $modelClass = '';
    
    private $__perNum = 10;
    public function beforeAction($action){
        parent::beforeAction($action);
        return true;
    }
    public function actionList(){
        $locationName = Yii::$app->request->post('locationName');
        $campType     = Yii::$app->request->post('campType');
        $keyword = Yii::$app->request->post('keyword');
        $page         = Yii::$app->request->post('page', 1);
        
        //查找指定数据
        if($campType){
            $campId = array();
            $arrCampType = array_filter(explode(Code::STR_SEPARATOR, $campType));
            $arrCampId = Search::find()->where(['content'=>$arrCampType, 'fieldName'=>Search::FIELD_TYPE])->asArray()->all();
            foreach ($arrCampId as $search){
                $campId[] = $search['campId'];
            }
            $campId = array_filter($campId);
        }
        
        $where = ['and'];
        $where[] = ['isDel' => Code::NOT_DEL_STATUS];
        $where[] = ['isStick' => 0];
        if(isset($campId) && $campId){
            $where[] = ['campId' => $campId];
        }
        if($locationName){
            $where[] = ['locationName' => array_filter(explode(Code::STR_SEPARATOR, $locationName))];
        }
        if($keyword){
            $where[] = ['like', 'title', $keyword];
        }
        
        $list = Campaign::find()
                        ->where($where)
                        ->orderBy(['updateTime'=>SORT_DESC])
                        ->limit($this->__perNum)
                        ->offset(($page - 1) * $this->__perNum)
                        ->asArray()
                        ->all();
        if(count($list)){
            foreach ($list as $k=>$campaign){
                $headImg = Content::find()
                                    ->where(['campId'=>$campaign['id'], 'fieldName' => Content::FIELD_HEAD_IMAGE])
                                    ->asArray()
                                    ->one();
                $list[$k]['headImg'] = Content::getImagePath($headImg['content']);
            }
        }
        $img_where = ['and'];
        $img_where[] = ['isDel'=>Code::NOT_DEL_STATUS];
        $img_where[] = ['>' , 'isStick', 0];
        $img_camp_arr = Campaign::find()
                                    ->where($img_where)
                                    ->orderBy(['isStick'=>SORT_DESC])
                                    ->asArray()
                                    ->all();
        $img_lunbo_arr = array();
        if(count($img_camp_arr)){
            foreach ($img_camp_arr as $camp){
                $img = Content::find()
                                    ->where(['campId' => $camp['id'], 'fieldName' =>Content::FIELD_IMAGE])
                                    ->orderBy(['id' => SORT_DESC])
                                    ->limit(1)
                                    ->asArray()
                                    ->one();
                $img_lunbo_arr[] = array(
                    'id' => $camp['id'],
                    'img' => Content::getImagePath($img['content']),
                );
            }
        }
        
        $model_campaign = new Campaign();
        
        $arr = $model_campaign->getLocationName();
        $arr = XUtils::my_sort($arr, 'count', SORT_DESC);
        
        return json_encode(array(
            'code' => Code::SUCC,
            'info' => Code::$arr_code_status[Code::SUCC],
            'data' => array(
                'list' => $list,
                'img'  => $img_lunbo_arr,
                'campTypeArr' => Campaign::$campTypeArr,
                'hotAreaArr'  => $arr,
            ),
        ), JSON_UNESCAPED_UNICODE);
    }   
    /**
    * @date: 2017年1月21日 下午5:25:31
    * @author: louzhiqiang
    * @return:
    * @desc:   获得详情
    */
    public function actionDetail(){
        $campId = Yii::$app->request->post('id');
        $page   = Yii::$app->request->post('page', 1);
        
        $info = Campaign::find()->where(['id'=>$campId])->asArray()->one();
        
        $image_arr = Content::find()
                                    ->where(['campId'=>$campId, 'fieldName'=>Content::FIELD_IMAGE])
                                    ->asArray()
                                    ->all();
        foreach ($image_arr as $img){
            $info['imageArr'][] = Content::getImagePath($img['content']);
        }
        
        $line_introduction = Content::find()
                                    ->where(['campId'=>$campId, 'fieldName'=>Content::FIELD_lINE_INTRODUCTION])
                                    ->asArray()
                                    ->one();
        $info[Content::FIELD_lINE_INTRODUCTION] = $line_introduction['content'];
        
        $expense_explanation = Content::find()
                                    ->where(['campId'=>$campId, 'fieldName'=>Content::FIELD_EXPENSE_EXPLANATION])
                                    ->asArray()
                                    ->one();
        $info[Content::FIELD_EXPENSE_EXPLANATION] = $expense_explanation['content'];
        
        $more_introduction = Content::find()
                                    ->where(['campId'=>$campId, 'fieldName'=>Content::FIELD_MORE_INTRODUCTION])
                                    ->asArray()
                                    ->one();
        $info[Content::FIELD_MORE_INTRODUCTION] = $more_introduction['content'];
        
        $scheduling = Content::find()
                                    ->where(['campId'=>$campId, 'fieldName'=>Content::FIELD_SCHEDULING])
                                    ->asArray()
                                    ->one();
        $info[Content::FIELD_SCHEDULING] = $scheduling['content'];
       
       //评价数据
       $evaluate_arr = Evaluate::find()
                                ->where(['campId'=>$info['id']])
                                ->orderBy(['createTime' => SORT_DESC])
                                ->limit($this->__perNum)
                                ->offset(($page - 1) * $this->__perNum)
                                ->asArray()
                                ->all();
       
       $info['evaluateArr'] = $evaluate_arr;
       
       return json_encode(array(
           'code' => Code::SUCC,
           'info' => Code::$arr_code_status[Code::SUCC],
           'data' => $info,
       ), JSON_UNESCAPED_UNICODE);
    }
    public function afterAction($action, $result){
        exit($result);
    }
}