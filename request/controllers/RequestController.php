<?php

namespace backend\modules\request\controllers;

use backend\components\ApiController;
use backend\components\ApiException;
use backend\components\PhoneValidator;
use backend\modules\request\jobs\PaymentJob;
use backend\modules\request\models\Request;
use backend\modules\request\models\RequestForm;
use backend\modules\user\models\User;
use common\helpers\SmsHelper;
use common\models\SmsCode;
use common\models\UserAction;
use yii\helpers\Url;
use yii\web\Response;

class RequestController extends ApiController
{

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['bearerAuth']['except'][] = 'list-agreement';
        $behaviors['bearerAuth']['except'][] = 'auto-tips';

        return $behaviors;
    }

    public function actionSaveDraft($id = null)
    {
//        response()->format = Response::FORMAT_RAW;
        $model = new RequestForm();
        $model->setScenario(RequestForm::SCENARIO_SAVE_DRAFT);
        if ($id) {
            $request = Request::findOne($id);
            if (!$request) {
                throw new ApiException('Заявка не найдена');
            }
            $model->setRequest($request);
        }
        $model->load(request()->post(), '');
        if (($request = $model->save())) {
            return ['id' => $request->id];
        }
    }

    public function actionCode($id)
    {
//        response()->format = Response::FORMAT_RAW;
        $model   = new RequestForm();
        $request = Request::findOne($id);
        if (!$request) {
            throw new ApiException('Заявка не найдена');
        }
        $model->setRequest($request);
        if (($request = $model->genVerifyCode())) {
            return ['code' => $request->verify_code];
        }
    }

    public function actionImages($id)
    {
        $request = Request::findOne($id);
        if (!$request) {
            throw new ApiException('Заявка не найдена');
        }
        if ($request->user_id != user()->id) {
            throw new ApiException('Заявка не найдена');
        }

        $images = [];
        foreach ($request->getFiles() as $file) {
            $images[] = [
                'id'    => $file->id,
                'label' => $file->label,
                'name'  => Request::labelImage($file->label),
         //     'src'   => $file->sourceBase64()
                'type'  => $file->mime,
            ];
        }

        return $images;

    }
    
    public function actionImage($id, $imageId)
    {
        $request = Request::findOne($id);
        if (!$request) {
            throw new ApiException('Заявка не найдена');
        }
        if ($request->user_id != user()->id) {
            throw new ApiException('Заявка не найдена');
        }
        $file = $request->getFile($imageId);

        $img = [
            'id'    => $file->id,
            'label' => $file->label,
            'name'  => Request::labelImage($file->label),
            'src'   => $file->sourceBase64()
        ];
        
        return $img;

    }

    public function actionSave($id = null)
    {
        $model = new RequestForm();
        if ($id) {
            $request = Request::findOne($id);
            if (!$request) {
                throw new ApiException('Заявка не найдена');
            }
            $model->setRequest($request);
        }
        $model->load(request()->post(), '');
        if (($request = $model->save())) {
            return ['id' => $request->id];
        }
    }

    public function actionDraft()
    {
        $request = Request::find()->lastDraftByUser(user()->id)->one();
        if (!$request) {
            throw new ApiException('Заявка не найдена');
        }

        return $request;
    }

    public function actionActive()
    {
        $request = Request::find()->byUser(\user()->id)->lastActive()->one();
        if (!$request) {
            throw new ApiException('Активной заявки не найдено');
        }

        return $request;
    }

    public function actionFinished()
    {
        $request = Request::find()->finished()->byUser(user()->id)->orderBy(['date' => SORT_DESC])->one();
        if (!$request) {
            throw new ApiException('Заявка не найдена');
        }

        return $request;
    }

    public function actionRequestSendSms($phone)
    {
        $phone = PhoneValidator::makePhone($phone);
        $userQuery  = User::find()->where(['phone' => $phone]);

        if(!user()->isGuest) {
            $userQuery->andWhere(['!=', 'id', user()->id]);
        }
        $user = $userQuery->one();
        if ($user) {
            throw new ApiException('Номер телефона закреплён за другим пользователем');
        }
        $sms = SmsHelper::sendCode(SmsCode::TYPE_VERIFY_USER_PHONE, 'Код подтверждения: {code}', $phone);

        return ['end_date' => $sms->end_date, 'attempt' => $sms->attempt];
    }

    public function actionRequestVerifyPhone($phone, $code)
    {
        $sms = SmsHelper::verifyCode(SmsCode::TYPE_VERIFY_USER_PHONE, $code, $phone);
        if (!$sms) {
            throw new ApiException('Неизвестная ошибка');
        }

        if (!user()->isGuest) {
            /* @var $user User */
            $user                 = user()->identity;
            $user->phone          = $phone;
            $user->phone_verified = 'Y';
            $user->save();
        }

        return [];
    }

    public function actionPaymentProcessing($id) {

        $request = Request::findOne($id);
        if (!$request) {
            throw new ApiException('Заявка не найдена');
        }
        if ($request->type != Request::TYPE_AGREED) {
            throw new ApiException('Заявка недоступна');
        }
        if ($request->user_id != user()->id) {
            throw new ApiException('Заявка недоступна!');
        }
        $code = \request()->post('code');
        $sms = SmsHelper::verifyCode(SmsCode::TYPE_REQUEST_AGREED, $code, $request->phone);

        if($sms) {
            $request->type = Request::TYPE_PAYMENT_PROCESSING;
            $request->save();
            \Yii::$app->queuePayment->push(new PaymentJob(['request' => $request]));

        }

        return [];

    }

    /**
     * Resend request sms code to user
     *
     * @param int $id
     *
     * @throws \Exception Request Not Found
     *
     * @return array
     */
    public function actionResendAgreed($id)
    {
        $request = Request::findOne(['id' => $id, 'user_id' => user()->id]);
        if (!$request) {
            throw new ApiException('Заявка не найдена');
        }

        $text = 'Заявка одобрена. Код подтверждения оферты {code}';
        UserAction::add(UserAction::ACTION_REQUEST_AGREE, null, null, $request->user_id, null);
        return SmsHelper::resendCode(SmsCode::TYPE_REQUEST_AGREED, $request->phone, $text);
    }

    /**
     * Check time for send request sms code to user
     *
     * @param int $id
     *
     * @throws \Exception Request Not Found
     *
     * @return array
     */
    public function actionCheckAgreed($id)
    {
        $request = Request::findOne(['id' => $id, 'user_id' => user()->id]);
        if (!$request) {
            throw new ApiException('Заявка не найдена');
        }

        $time = SmsHelper::getCodeTimeLeft(SmsCode::TYPE_REQUEST_AGREED, $request->phone);

        return ['time_left' => $time];
    }

    public function actionListAgreement()
    {
        return [
            [
                'label' => 'Направляя в адрес ООО «МКК ЭЛВАС», (ОГРН 1186196050930, ИНН 6162080106) (далее - Общество), заявку-анкету на предоставление микрозайма в электронном виде, посредством своего Личного кабинета (далее-Заявка) я подтверждаю свое согласие:'
            ],
            [
                'name' => 'legal_kbi',
                'label' => '  C обращением ООО «МКК ЭЛВАС» в бюро кредитных историй для получения кредитного отчета, состав которого определяется в соответствии с Федеральным Законом от 30.12.2004 г. № 218-ФЗ «О кредитных историях», в том числе для проверки сведений, указанных в настоящей Заявке;',
                'document_id' => 9,
                'link_name' => 'см.: Согласие на получение кредитного отчета.'
            ],
            [
                'name' => 'legal_family',
                'label' => 'Осуществлять взаимодействие с любыми третьими лицами, под которыми понимаются мои члены семьи, родственники, иные проживающие со мной лица, соседи, а также любые иные физические лица. Под взаимодействием с третьим лицами, понимается в том числе передача (информирование) любым доступным способом вышеуказанным третьим лицам мои персональные данные, ставшие известные Обществу при заключении договора микрозайма и в течении срока действия такого договора;',
            ],
            [
                'name' => 'legal_colector',
                'label' => 'Предоставления моих персональных данных третьему лицу с целью передачи Обществом принадлежащих ему функций и полномочий иному лицу (в том числе, для совершения действий, направленных на возврат просроченной задолженности), а равно при привлечении третьих лиц к оказанию услуг в указанных целях, Общество вправе в необходимом объеме раскрывать (передавать) для совершения вышеуказанных действий информацию обо мне лично (включая мои персональные данные) таким третьим лицам, в том числе, информацию о моей просроченной задолженности и её взыскании, а также предоставлять таким третьим лицам соответствующие документы, содержащие такую информацию;',
                'document_id' => 8,
                'link_name' => 'см. Согласие на обработку персональных данных и осуществления взаимодействия с клиентом.'
            ],
            [
                'name' => 'legal_v',
                'label' => 'Проставляя виртуальную отметку «V», я подтверждаю заключение между мной и ООО «МКК ЭЛВАС» соглашения об использовании электронной подписи на следующих условиях;',
                'document_id' => 10,
                'link_name' => 'см.: Соглашение об использовании электронной подписи.'
            ],
            [
                'name' => 'legal_asp',
                'label' => 'Согласен на использование аналога собственноручной подписи (далее - АСП), в качестве которого рассматривается простая электронная подпись, формируемая в соответствии с требованиями законодательства Российской Федерации;',
            ],
            [
                'name' => 'legal_agreement',
                'label' => 'Согласен на заключение Соглашения о вопросах взаимодействия при возникновении просроченной задолженности по договору микрозайма (далее - Соглашение). В случае согласия, я подтверждаю, что ознакомлен с условиями Соглашения;',
            ],
            [
                'name' => 'legal_breach',
                'label' => 'Согласен, что в случае неисполнения или ненадлежащего исполнения мной условий договора микрозайма, Общество вправе уступить свои права (требования) по договору микрозайма третьим лицам;',
            ],
            [
                'name' => 'legal_ad',
                'label' => 'Я согласен на получение информации рекламного характера по номеру телефона и адресу электронной почты, указанных мной при заполнении Анкеты. Я предоставляю ООО «МКК ЭЛВАС» и иным третьим лицам, которым могут быть переданы мои данные, право на направление рекламной информации по контактным данным (телефон и электронная почта), указанным мной при заполнении Заявки на предоставление микрозайма;',
            ],
            [
                'name' => 'legal_accept',
                'label' => 'Направляя данную форму Заявки в ООО «МКК ЭЛВАС», я заявляю (подтверждаю): <br />
- до направления Заявки мне предоставлена вся необходимая информация, установленная ст. 3 Базового стандарта защиты прав и интересов физических и юридических лиц - получателей финансовых услуг. Полученная информация является достаточной для принятия обоснованного решения о целесообразности заключения договора микрозайма на предлагаемых условиях, мною проанализировано мое финансовое положение, в том числе, для правильной оценки рисков по обязательству;<br />
- что до меня доведена информация о том, что предоставленные мною сведения о размере заработной платы, наличии иных источников дохода и денежных обязательствах, могут оказать влияние на индивидуальные условия договора микрозайма, заключаемого между мною и Обществом, мне разъяснены риски, связанные с ненадлежащим исполнением обязательств по договору микрозайма, и доведена информация о возможных негативных финансовых последствиях при использовании услуги по получению микрозайма, мне предоставлена информация, достаточная для принятия обоснованного решения о целесообразности заключения договора микрозайма на имеющихся у Общества условиях;<br />
- в отношении меня на протяжении последних 5 лет отсутствовали и отсутствуют факты производства по делу о банкротстве;<br />
- что я не являюсь иностранным налогоплательщиком, не имею одновременно с гражданством Российской Федерации гражданства иностранного государства, не имею вида на жительство в иностранному государстве, не являюсь публичным должностным лицом, у меня отсутствуют связи с публичными должностными лицами, а также лицами, замещающих (занимающих) государственные должности РФ, должности членов совета директоров Банка России, должности федеральной государственной службы, назначение на которые и освобождение от которых осуществляются Президентом РФ или Правительством РФ, должности в Банке России, государственных корпорациях и иных организациях, созданных Российской Федерацией на основании федеральных законов, включенные в перечни должностей, определяемые Президентом РФ;<br />
- что я, действую самостоятельно, без принуждения, в своем интересе, в моей деятельности и в планируемых к совершению операциях (сделкам) отсутствуют операции с участием выгодоприобретателя;<br />
- бенефициарным владельцем и (или) физическим лицом, имеющим право распоряжаться моими полномочиями при совершении финансовых операций, принимаю самого себя;<br />
- что я уведомлен о том, что Общество вправе предоставлять информацию в бюро кредитных историй в соответствии с Федеральным законом № 218-ФЗ от 30.12.2004 года «О кредитных историях», по поданным мной заявкам на предоставление микрозайма, заключаемым с Обществом, договорам займа, всех изменений к ним, а также на предоставление персональных данных и иной информаций, необходимой для идентификации клиента Общества;<br />
- что я ознакомлен и согласен с Правилами предоставления микрозаймов, Общими условиями договора микрозайма, в том числе о порядке и условиях предоставления микрозайма, сроках и порядке возврата суммы микрозайма, о своих правах и обязанностях, о порядке изменения условий договора, платежах, связанных с получением и возвратом микрозайма, нарушением условий договора микрозайма и до принятия оферты о заключении договора микрозайма получил всю необходимую информацию.<br />'
            ],
        ];
    }

    /**
     * Возвращает подсказки для заполнения авто
     *
     * @param $request_id
     * @param $type
     * @param $query
     * @return string|null
     * @throws ApiException
     */
    public function actionAutoTips($request_id, $type, $query)
    {
        $request = Request::findOne(['id' => $request_id]);
        if (!$request) {
            throw new ApiException('Заявка не найдена');
        }

        try {
            return \Yii::$app->autoCode->getData($request, $type, $query);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }
}