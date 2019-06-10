<?php

namespace backend\modules\request\controllers;

use backend\components\ApiController;
use backend\components\ApiException;
use backend\modules\contract\models\Contract;
use backend\modules\request\models\RequestSchedule;
use backend\modules\user\models\User;

class ScheduleController extends ApiController
{
    /**
     * Get Schedule List
     *
     * @throws ApiException
     *
     * @return array
     */
    public function actionList()
    {
        $user = User::findOne(user()->id);
        if (!$user) {
            throw new ApiException('Пользователь не найден');
        }

        $contract = Contract::findOne(['user_id' => $user->id, 'status' => Contract::STATUS_ACTIVE]);
        if (!$contract) {
            throw new ApiException('Договор не найден');
        }

        $requestSchedule = RequestSchedule::findOne(['request_id' => $contract->request_id]);
        if (!$requestSchedule) {
            throw new ApiException('График не найден');
        }

        $response = json_decode($requestSchedule->response, true);

        return $response['ГрафикПлатежей'];
    }
}