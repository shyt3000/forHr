<?php


namespace backend\modules\request\jobs;


use common\helpers\AutoParsingHelper;
use backend\modules\request\models\RequestUmfo;
use yii\queue\db\Queue;
use backend\modules\request\models\Request;
use Yii;

class AutoParsingCheckJob extends \yii\base\BaseObject implements \yii\queue\JobInterface
{

    public $request;

    public $queueDromId;
    public $queueAutoMamaId;
    public $queueAutoRuId;
    /**
     * @param Queue $queue which pushed and is handling the job
     * @return void|mixed result of the job execution
     */
    public function execute($queue)
    {
        $request = $this->request;
        echo 'Start Job: ', $this->request->id, PHP_EOL;

        if (
            Yii::$app->queue->isDone($this->queueDromId) ||
            Yii::$app->queue->isDone($this->queueAutoMamaId) ||
            Yii::$app->queue->isDone($this->queueAutoRuId)
        )
        {
            $r = Request::findOne($this->request->id);
            echo 'saveAverage', PHP_EOL;
            $r->saveAverage();

            echo 'UMFO request', PHP_EOL;
            $r->requestUmfo->createApplicationByRequest($r);
        } else {
            echo 'No one parser is done', $this->request->id, PHP_EOL;
        }
        echo 'End Job: ', $this->request->id, PHP_EOL;
    }
}