<?php

namespace backend\modules\request\jobs;

use common\models\Transaction;
use common\helpers\echoHelper;

class PaymentTransactionJob extends \yii\base\BaseObject implements \yii\queue\JobInterface {

    /** @var \common\models\Transaction */
    public $transaction;
    public $paymentRequest;

    public function execute($queue)
    {
        echo 'Transactin: #'.$this->transaction->id.PHP_EOL;

        $system = $this->transaction->getPaymentSystem();

        //TODO: удалить
        echo 'Payment System:' . PHP_EOL;
        \Yii::warning('this->paymentRequest: ' . var_dump($this->paymentRequest), 'YandexTrasactionJOB');
        $status = $system->pay($this->paymentRequest, $this->transaction);
        //TODO: удалить
        echo 'Payment status: ' . PHP_EOL;
        \Yii::warning('Payment status' . var_dump($status), 'YandexTrasactionJOB');

        if($status == Transaction::STATUS_PROCESSING) {
            //echoHelper::pr($this->transaction->requestDT, 0);
            //echoHelper::pr(date('Y-m-d H:i:s'), 0);
            echo 'Статус в процессе, следующая попытка будет '.$this->transaction->requestDT.PHP_EOL;
            $dalay = strtotime($this->transaction->requestDT) - time();
            $dalay = $dalay < 0 ? 0 : $dalay;
            //echoHelper::pr($dalay, 0);
            \Yii::$app->queuePaymentTransaction->delay($dalay)->push(new PaymentTransactionJob([
                'paymentRequest' => $this->paymentRequest, 'transaction' => $this->transaction
            ]));
        }

        echo 'Transactin processed OR ERROR: #'.$this->transaction->id.PHP_EOL;

    }

}