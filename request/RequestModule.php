<?php

namespace backend\modules\request;

class RequestModule extends \yii\base\Module {

	public $controllerNamespace = 'backend\modules\request\controllers';

    public static function getUrlRules() {
        return [
            'POST /request/save-draft' => '/request/request/save-draft',
            'POST /request/save-draft/<id:\d+>' => '/request/request/save-draft',
            'POST /request/save/<id:\d+>' => '/request/request/save',
            '/request/code/<id:\d+>' => '/request/request/code',
            '/request/draft' => '/request/request/draft',
            '/request/active' => '/request/request/active',
            '/request/finished' => '/request/request/finished',
            '/request/images/<id:\d+>' => '/request/request/images',
            '/request/<id:\d+>/image/<imageId:\d+>' => '/request/request/image',
            '/request/send-sms' => '/request/request/request-send-sms',
            '/request/verify-phone' => '/request/request/request-verify-phone',
            'POST /request/payment-processing/<id:\d+>' => '/request/request/payment-processing',
            '/request/list-agreement' => '/request/request/list-agreement',
            'POST /request/auto-tips' => '/request/request/auto-tips',

            '/request/<requestId:\d+>/document/<docId:\d+>' => '/request/document/get',
            '/request/<requestId:\d+>/document/list' => '/request/document/list',
            '/request/<requestId:\d+>/document/list/agreement' => '/request/document/list-agreement',
            '/request/<requestId:\d+>/document/<docId:\d+>/sum/<sum:[+-]?\d+(?:\.\d+)?>/' => '/request/document/get',

            'POST /request/resend-agreed/<id:\d+>' => '/request/request/resend-agreed',
            '/request/check-agreed/<id:\d+>' => '/request/request/check-agreed',

            '/verify/email-send' => '/request/verify/email-send',
            '/verify/email-verify/<code:\d+>' => '/request/verify/email-verify',

            '/request/schedule/list' => '/request/schedule/list',

            //потестирую , что вылилось
            '/test' => '/request/test/test',
            '/test/create-payment' => '/request/test/create',
            '/test/set-limits' => '/request/test/set-limits',
            '/test/complete-payment' => '/request/test/complete-payment',
            '/test/create-transections' => '/request/test/create-transactions',
            '/test/get-avalible' => '/request/test/get-avalible',
            '/test/set-respond-error' => '/request/test/set-respond-error',
            '/test/set-respond-done' => 'request/test/set-respond-done',
            '/test/set-respond-process' => 'request/test/set-respond-process',
            '/test/create-application' => '/request/test/create-application',
            '/test/user/payment' => '/request/test/user-payment'
        ];
    }

}
