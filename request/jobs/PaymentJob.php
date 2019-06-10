<?php


namespace backend\modules\request\jobs;

use common\models\RequestPayment;
use common\models\Transaction;
use yii\helpers\ArrayHelper;
use backend\modules\contract\models\Contract;
use backend\modules\request\models\Request;
use common\models\UserAction;

class PaymentJob extends \yii\base\BaseObject implements \yii\queue\JobInterface
{

    /** @var Request */
    public $request;

    public function execute($queue)
    {
        $request = $this->request;
        echo 'Start Job. Request: ', $this->request->id, PHP_EOL;

        $dbTransaction = \Yii::$app->db->beginTransaction();
        try {
            if (!$request) {
                throw new \Exception('Заявка не найдена');
            }
            if ($request->type != Request::TYPE_PAYMENT_PROCESSING) {
                throw new \Exception('Заявка недоступна');
            }
            if(!$request->requestUmfo) {
                throw new \Exception('заявка в УМФО не создана');
            }
            if(!$request->requestUmfo->loan_number) {
                $request->requestUmfo->createLoanByRequest($request);
            }
            $request->requestUmfo->createDocDepositByRequest($request);
            $schedule = $request->requestUmfo->createSchedule();

            $createDate = ag($schedule, 'Дата');
            //            $sumDebt = ag($schedule, 'ПолнаяСтоимостьЗайма');
            $sumDebt = $request->sum;
            $dateNextPayment = ArrayHelper::getValue($schedule, 'ГрафикПлатежей.0.ДатаПлатежа');
            $sumNextPayment = ArrayHelper::getValue($schedule, 'ГрафикПлатежей.0.СуммаПлатежа');

            $contract = new Contract();
            $contract->status = Contract::STATUS_ACTIVE;
            $contract->sum = $sumDebt;
            $contract->sum_debt = $sumDebt;
            $contract->user_id = $request->user_id;
            $contract->request_id = $request->id;
            $contract->date_next_payment = date('Y-m-d', strtotime($dateNextPayment));
            $contract->sum_next_payment = $sumNextPayment;
            $contract->date_start = date('Y-m-d', strtotime($createDate));
            $contract->date_end = date('Y-m-d', strtotime('+'.$request->sum_time.' months', strtotime($contract->date_start)));
            if(!$contract->save()) {
                \Yii::error('Contract Error' . var_dump($contract->getErrors()), 'contract');
                throw new \Exception('Error save contract.');
            }
            UserAction::add(UserAction::ACTION_GENERATED_DOCUMENTS, $request->id, null, $request->user_id);

            $paymentRequest = RequestPayment::saveRequestPayment($contract);
            $transactions = Transaction::getAvailableByPaymentRequest($paymentRequest->id);

            foreach ($transactions as $transaction) {
                $dalay = strtotime($transaction->requestDT) - time();
                $dalay = $dalay < 0 ? 0 : $dalay;
                \Yii::$app->queuePaymentTransaction->delay($dalay)->push(new PaymentTransactionJob(compact('paymentRequest', 'transaction')));
            }

            $request->type = Request::TYPE_PAYMENT_SUCCESS;
            if(!$request->save()) {
                \Yii::error('Request Payment ' . var_dump($request->getErrors()), 'Request Payment');
                throw new \Exception('Error save request');
            }
            $dbTransaction->commit();

        } catch (\Throwable $e) {
            $dbTransaction->rollBack();
            $request->updateAttributes(['type' => Request::TYPE_PAYMENT_ERROR]);
            echo $e->getMessage();
            throw $e;
        }
    }
}