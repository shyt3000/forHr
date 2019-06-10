<?php

namespace backend\modules\request\jobs;

use backend\modules\request\models\RequestUmfo;

class checkResultFSSPJob extends \yii\base\BaseObject implements \yii\queue\JobInterface
{
    /** @var Request */
    public $request;

    public function execute($queue)
    {
        $request = $this->request;
        echo 'Start Job: ', $this->request->id, PHP_EOL;

        $requestUmfo = RequestUmfo::findOne(['request_id' => $request->id]);

        if(!$requestUmfo) {
            return;
        }
        if ((($requestUmfo->updated_at - $requestUmfo->created_at) / 60) > 3) {
            $requestUmfo->status = RequestUmfo::STATUS_TIMEOUT;
            $requestUmfo->save(false);
        }
        $task = $requestUmfo->fssp_task;
        if ($requestUmfo->fssp_status == 2) {
            echo 'Status' . PHP_EOL;
            $result = $requestUmfo->getApiUmfo()->getStatusFSSP($task);
            dump($result, 0);

            if($result['СтатусПроверки'] == 'Выполнено') {
                $requestUmfo->fssp_status = 1;
                echo 'Result' . PHP_EOL;
                $result = $requestUmfo->getApiUmfo()->getResultFSSP($task);

                $requestUmfo->status      = RequestUmfo::STATUS_COMPLETED;
                $requestUmfo->fssp_result = ag($result, 'РезультатПроверки');
                $requestUmfo->request_rejected = ag($request, 'ОтклонитьЗаявку');
                dump(json_decode($requestUmfo->fssp_result), 0);
            }
            elseif($result['СтатусПроверки'] == 'Ошибка при выполнении') {
                $requestUmfo->fssp_status = 3;
                $requestUmfo->status = RequestUmfo::STATUS_COMPLETED;
            }

            $requestUmfo->updated_at = time();
            $requestUmfo->save(false);
        }

        if($requestUmfo->status == RequestUmfo::STATUS_ACTIVE) {
            \Yii::$app->queuePrescoring->delay(30)->push(new checkResultFSSPJob(['request' => $request]));
        }
    }
}