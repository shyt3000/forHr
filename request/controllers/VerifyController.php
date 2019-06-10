<?php

namespace backend\modules\request\controllers;

use backend\components\ApiController;
use backend\components\ApiException;
use common\models\VerifyEmail;

/**
 * Class VerifyController
*/
class VerifyController extends ApiController
{
    /**
     * Send verify code to email
     *
     * @return array
     */
    public function actionEmailSend()
    {
        $verifyEmail = VerifyEmail::findOne(['user_id' => user()->id]);
        $verifyEmail = $verifyEmail ?: new VerifyEmail();
        $verifyEmail->user_id = user()->id;
        $verifyEmail->email = user()->identity->email;
        $verifyEmail->code = rand(111111, 999999);
        $verifyEmail->status = VerifyEmail::STATUS_PENDING;

        $verifyEmail->sendVerificationEmail();
        $verifyEmail->save();

        return ['send' => true];
    }

    /**
     * Verify email code
     *
     * @param string $code
     *
     * @throws ApiException Invalid Email
     *
     * @return array
     */
    public function actionEmailVerify($code)
    {
        $verifyEmail = VerifyEmail::findOne(['code' => $code]);

        if (!$verifyEmail) {
            throw new ApiException('Проверочный код не найден.');
        }

        $verifyEmail->status = VerifyEmail::STATUS_VERIFIED;
        $verifyEmail->save();

        $verifyEmail->user->email_verified = 'Y';
        $verifyEmail->user->save();

        return ['verify' => true];
    }
}
