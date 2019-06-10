<?php

namespace backend\modules\request\jobs;

use backend\modules\request\models\Request;
use backend\modules\request\models\RequestUmfo;

class PrescoringJob extends \yii\base\BaseObject implements \yii\queue\JobInterface
{

    /** @var Request */
    public $request;

    public function execute($queue)
    {
        $request = $this->request;
        echo 'Start Job: ', $this->request->id, PHP_EOL;

        $requestUmfo = RequestUmfo::findOne(['request_id' => $request->id]);

        try {
            if(!$requestUmfo) {
                $requestUmfo             = new RequestUmfo();
                $requestUmfo->request_id = $request->id;
                $requestUmfo->checkClientExistsByUser($request->user);
                $requestUmfo->createClientByRequest($request);
                $requestUmfo->createDepositByRequest($request);
                $requestUmfo->createApplicationByRequest($request);
            } else {
                $requestUmfo->status = RequestUmfo::STATUS_ACTIVE;
                $requestUmfo->created_at = time();
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkClientFSSPByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkClientTerroristByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkClientFROMUByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkClientBlockedListByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkClientFMSByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkBlackListRegionByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkPassportExpiredByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkClientAgeByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkClientListRefusersByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkAvtoVINRZByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkClientBankruptByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
                echo $requestUmfo->fssp_task . PHP_EOL;
            }
            if(!$requestUmfo->request_rejected){
                try {
                    $requestUmfo->checkBlacklistMFByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            if(!$requestUmfo->request_rejected){
                try{
                    $requestUmfo->checkClientCreditScoreEquiFaxByRequest($request);
                } catch (\Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    \Yii::error($e);
                }
            }
            
            if (!$requestUmfo->save()) {
                dump($requestUmfo->getFirstErrors(), 0);
                throw new \Exception('Save request error');
            }
            if($requestUmfo->fssp_task) {
                \Yii::$app->queuePrescoring->push(new checkResultFSSPJob(['request' => $request]));
            }
        }
        catch (\Exception $e) {
            \Yii::error($e);
            //                app()->mailer->compose()
            //                    ->setFrom([app()->params['supportEmail'] => app()->name . ' robot'])
            //                    ->setTo('gubakv@avto-zaim.online')
            //                    ->setSubject('Report UMFO')
            //                    ->setTextBody($e->getMessage())
            //                    ->send();
            echo 'Ошибка: '. $e->getMessage().PHP_EOL;
            echo $e->getTraceAsString();
        }
    }
}