<?php

namespace backend\modules\request\helper;

use backend\components\ApiException;
use backend\modules\request\models\RequestSchedule;

/**
 * Class RequestScheduleHelper
 */
class RequestScheduleHelper
{
    /**
     * @param integer $requestId
     * @param array   $response
     *
     * @throws ApiException
     *
     * @return RequestSchedule
     */
    static public function createRequestSchedule(int $requestId, array $response)
    {
        $requestSchedule = RequestSchedule::findOne(['request_id' => $requestId]) ?: new RequestSchedule();
        $requestSchedule->response = json_encode($response);
        $requestSchedule->request_id = $requestId;
        $requestSchedule->created_at = date('Y-m-d H:i:s');
        $requestSchedule->updated_at = date('Y-m-d H:i:s');

        if (!$requestSchedule->save()) {
            throw new ApiException($requestSchedule->getFirstErrors());
        }

        return $requestSchedule;
    }
}
