<?php


namespace backend\modules\request\controllers;


use backend\components\ApiController;
use backend\modules\request\models\Request;
use backend\modules\request\models\RequestDocument;
use backend\modules\request\models\RequestUmfo;
use common\helpers\LabelHelper;
use kartik\mpdf\Pdf;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\NotFoundHttpException;
use backend\modules\user\models\LoanSettlements;
use backend\modules\contract\models\Contract;
use backend\modules\request\models\UmfoDoc;

class DocumentController extends ApiController
{


//    public function behaviors()
//    {
//        $behaviors                           = parent::behaviors();
//        $behaviors['bearerAuth']['except'][] = 'get';
//
//        return $behaviors;
//    }

    public function actionGet($requestId, $docId, $sum = null)
    {

        app()->response->format = \yii\web\Response::FORMAT_RAW;

        $request = Request::findOne($requestId);
        if (!$request) {
            throw new NotFoundHttpException();
        }

        if ($request->user_id != user()->id) {
            throw new NotFoundHttpException();
        }

        $document = RequestDocument::findOne($docId);
        if (!$document) {
            throw new NotFoundHttpException();
        }
        //страховка
        if ($docId == 13) {
            $file = fileStorage()->basePath. "/" .$this->getInsurance($requestId);
            if (file_exists($file)) {
                return \Yii::$app->response->sendFile($file);
            }
            throw new \Exception('File not found in path ' . $file);
        }
        //автокод
        if ($docId == 14) {
            $file = fileStorage()->basePath. "/" .$this->getAutoCod($requestId);
            if (file_exists($file)) {
                return \Yii::$app->response->sendFile($file);
            }
            throw new \Exception('File not found in path ' . $file);
        }

        $template = $document->template;

//        ob_start();
//        include('temp/' . $document->id . '.php');
//        $template = ob_get_contents();
//        ob_end_clean();

        $content = t('app', $template, $this->getVariables($request, $docId, $sum));

        $pdf = new Pdf([
            'mode'         => Pdf::MODE_UTF8, // leaner size using standard fonts
            'destination'  => Pdf::DEST_BROWSER,
            'content'      => $content,
            'marginTop'    => in_array($docId, [7, 5]) ? 6 : 16,
            'marginBottom' => in_array($docId, [7, 5]) == 7 ? 0 : 16,
            'cssInline'    => '    table.border {
        border-collapse: collapse;
    }
    table.border th {
        border: 0.5pt solid #000;
    }
    table.border td {
        border: 0.5pt solid #000;
    }',
            //            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'options'      => [
                'defaultfooterline'      => false,
                'defaultheaderfontstyle' => '',
                'defaultheaderfontsize'  => 7,
                'defaultheaderline'      => false,
                // any mpdf options you wish to set
            ],
            'methods'      => [
                'SetTitle'    => app()->name,
                'SetSubject'  => '',
                'SetHeader'   => [$document->header],
                'SetFooter'   => [$document->footer],
                'SetAuthor'   => '',
                'SetCreator'  => '',
                'SetKeywords' => '',
            ]
        ]);

        return $pdf->render();
    }

    public function getInsurance($requestId)
    {
        $request = Request::findOne($requestId);
        if (!$request) {
            throw new NotFoundHttpException();
        }

        $document = UmfoDoc::findOne(['request_id' => $request->id, 'type' => UmfoDoc::TYPE_INSURANSE]);
        if (!$document) {
            $document = $request->requestUmfo->createInsuranceRenessansByRequest($request);
        }
        return $document->path;
    }

    public function getAutoCod($requestId)
    {
        $request = Request::findOne($requestId);
        if (!$request) {
            throw new NotFoundHttpException();
        }

        $document = UmfoDoc::findOne(['request_id' => $request->id, 'type' => UmfoDoc::TYPE_AUTO]);
        if (!$document) {
            $document = $request->requestUmfo->createAReportAutokod($request);
        }
        return $document->path;
    }

    public function actionList($requestId)
    {
        $request = Request::findOne($requestId);
        if (!$request) {
            throw new NotFoundHttpException();
        }
        $id = [1, 6, 7];

        $requests = Request::find()
            ->byUser(\user()->id)
            ->andWhere(['type' => [Request::TYPE_PAYMENT_PROCESSING, Request::TYPE_PAYMENT_SUCCESS]])
            ->andWhere(['<>', 'id',  $request->id])
            ->count();

        if (!$requests) {
            $id[] = 5;
        }

        if ($request->sum_time <= 12){
            $id[] = 2;
        } else {
            $id[] = 3;
        }
        if($request->insurance == 'Y' || $request->auto_report == 'Y'){
            $id[] = 4;
        }
        if($request->insurance == 'Y' && $request->type != Request::TYPE_DRAFT) {
            $id[] = 13;
        }
        if($request->auto_report == 'Y' && $request->type != Request::TYPE_DRAFT) {
            $id[] = 14;
        }

        return RequestDocument::find()->where(['id' => $id])->all();
    }

    public function actionListAgreement($requestId)
    {
        $request = Request::findOne($requestId);
        if (!$request) {
            throw new NotFoundHttpException();
        }

        return RequestDocument::find()->where(['id' => [8, 9, 10]])->all();
    }

    public function getVariables(Request $request, $docId, $sum = null)
    {

        $isAgreement = in_array($docId, [8, 9, 10]);

        $vars = [];
        foreach ($request->toArray() as $key => $value) {
            $vars[ 'request.' . $key ] = $value;
        }


        $vars['request.car_sts_date'] = date('d.m.Y', strtotime($request->car_sts_date));
        $vars['request.car_pts_date'] = date('d.m.Y', strtotime($request->car_pts_date));

        $n = $vars['request.sum_time'];
        switch ($n){
            case $n<2: 
                $vars['declination.month'] = 'месяц';
                break;
            case $n>=2 and $n<=4:
                $vars['declination.month'] = 'месяца';
                break;
            case $n>=5 and $n<=20:
                $vars['declination.month'] = 'месяцев';
            break;
        }
        $time                                    = strtotime($request->date);
        $vars['request.snils']                   = $request->snils ? $request->snils : '&mdash;';
        $vars['request.job_phone']               = $request->job_phone ? $request->job_phone : '&mdash;';
        $vars['request.debts_value']             = $request->debts == 'Y' ? 'Сумма долговых обязательств: ' . $request->debts_sum . ' руб., дата погашения обязательств: ' . date('d.m.Y', strtotime($request->debts_periodicity)) : 'Не имеются';
        $vars['request.additional_income_value'] = $request->additional_income == 'Y' ? 'Имеется' : 'Не имеется';
        $vars['request.date.day']                = date('j', $time);
        $vars['request.date.year']               = date('Y', $time);
        $vars['request.date.month_str']          = \Yii::$app->formatter->asDate($request->date, 'MMMM');
        $vars['request.sum_str']                 = num2str($request->sum);
        $vars['request.avg_estimated_str']       = num2str($request->avgEstimated);
        $vars['request.sex_label_full']          = LabelHelper::sexFull($request->sex);
        $vars['request.phone_7']                 = '+7' . $request->phone;
        
        switch ($request->payment_method) {
            
            case "card": 
                $vars['request.payment_name'] = "Единовременное перечисление сумы микрозайма на счет дебетовой пластиковой карты  заёмщика (платежной системы  VISA, MasterCard) Номер бан-ковской карты";
                break;

            case "yandexCard":
                $vars['request.payment_name'] = "Единовременное перечисление сумы микрозайма на счет дебетовой пластиковой карты  заёмщика (платежной системы  VISA, MasterCard) Номер бан-ковской карты";
                break;
       
            case "contact": 
                $vars['request.payment_name'] = "Выдача наличных денежных средств в офисе банка-партнёра платежной системы  «Контакт» (АО «КИВИ-БАНК»)";
                break;
            
            case "qiwi": 
                $vars['request.payment_name'] = "Единовременное перечисление сумы микрозайма на Qiwi кошелек заемщика номер Qiwi кошелька";
                break;
            
            case "yandex":
                $vars['request.payment_name'] = "Единовременное перечисление сумы микрозайма на Ян-декс.Кошелек заемщика номер Яндекс.Кошелька  ";
                break;
            
        }
        
        $vars['request.passport_number_hide']    = 'ХХХХ-ХХХХ-ХХХХ-' . substr($request->payment_number, -4);
        $vars['request.car_pts_special_dash']    = $request->car_pts_special ? $request->car_pts_special : '_______________';
        $vars['request.birthday_format']         = date('d.m.Y', strtotime($request->birthday));
        #выдачи
        $passportTime                            = strtotime($request->passport_date);
        $vars['request.passport_date']           = date('d.m.Y', $passportTime);
        $vars['request.passport_date.day']       = date('j', $passportTime);
        $vars['request.passport_date.year']      = date('Y', $passportTime);
        $vars['request.passport_date.month_str'] = \Yii::$app->formatter->asDate($request->passport_date, 'MMMM');
        $vars['request.passport_place_kod']      = $request->passport_place_kod;

        $vars['request.reg_kv'] = $request->reg_kv ? 'кв. ' . $request->reg_kv : null;

        $vars['date.day']       = date('j');
        $vars['date.year']      = date('Y');
        $vars['date.month_str'] = \Yii::$app->formatter->asDate(time(), 'MMMM');
        $vars['date.time']      = date('H:i');

        $regAddress = [];
        $regAddress[] = $request->reg_region;
        $regAddress[] = $request->reg_city;
        $regAddress[] = $request->reg_street;
        $regAddress[] = $request->reg_house;
        $regAddress[] = $request->reg_korpus;
        $regAddress[] = $request->reg_kv;
        $regAddress = array_filter($regAddress);
        $regAddress = implode(', ', $regAddress);
        $vars['request.reg_address_format']           = $regAddress;

        $vars['org.elvas.name']         = 'ООО "МКК ЭЛВАС"';
        $vars['org.elvas.inn']          = '6162080106';
        $vars['org.elvas.kpp']          = '616201001';
        $vars['org.elvas.ogrn']         = '1186196050930';
        $vars['org.elvas.ras_schet']    = '40701810503300000029';
        $vars['org.elvas.bank']         = 'ФИЛИАЛ ЮЖНЫЙ ПАО БАНКА "ФК ОТКРЫТИЕ"';
        $vars['org.elvas.bik']          = '046015061';
        $vars['org.elvas.kor_schet']    = '30101810560150000061';
        $vars['org.elvas.address']      = '344101, Ростовская обл, Ростов-на-Дону г, Ленинградская ул, дом № 7, кабинет 42';
        $vars['org.elvas.address_city'] = 'Ростов-на-Дону';
        $vars['org.elvas.director']     = 'Москальчук Ирина Юрьевна';
        $vars['org.elvas.phone']        = '8 800 600 90 19';
        $vars['org.elvas.email']        = 'info@autonomy.finance';

        $vars['product.name'] = 'Автономи';
        $vars['site']         = 'www.autonomy.finance';
        $vars['img_sign']     = '<img src="' . getenv('BACKEND_URL') . '/images/documents/sign.png" width="160">';
        $vars['sign_asp']     = '';
        if ($request->type != Request::TYPE_DRAFT && $docId != 11 && $docId != 12) {
            $vars['sign_asp'] = '<div style="float: right; width: 250px; border: 2px solid #000; font-size: 10px; padding: 2px;">
Документ подписан с использованием
аналога собственноручной подписи
(АСП).<br>
' . $request->fio . '<br>
' . $vars['date.day'] . ' ' . $vars['date.month_str'] . ' ' . $vars['date.year'] . ' года в ' . $vars['date.time'] . '
</div>';
        }
        $vars['sign_asp_success'] = '';
        if ($request->type == Request::TYPE_PAYMENT_SUCCESS) {
            $vars['sign_asp_success'] = $vars['sign_asp'];
        }

        $vars['two_sign'] = '<div style="padding-top: 70px; float: right; width: 30%">' . $vars[($isAgreement ? 'sign_asp': 'sign_asp_success')] . '</div>
    <div style="margin-left: 50px; padding-top: 90px; float: left; width: 200px">
        Директор ООО «МКК ЭЛВАС»
        <br>____________________ Москальчук И.Ю.
        <br>
    </div>
    <div style="float: left; margin-left: -250px; margin-top: 15px;">' . $vars['img_sign'] . '</div>';

        $vars['umfo.loan_number'] = '';
        try {
            if (!$request->requestUmfo->loan_number) {
                $request->requestUmfo->createLoanByRequest($request);
            }
            $vars['umfo.loan_number'] = $request->requestUmfo->loan_number;
        } catch (\Throwable $e) {
            \Yii::error($e);
        }

        $schedule = null;
        if ($docId == 2 || $docId == 3) {
            $paymentDates = [];
            try {
                $schedule = $request->requestUmfo->createSchedule();
                $graphs   = ag($schedule, 'ГрафикПлатежей', []);
            } catch (\Throwable $e) {
                \Yii::error($e);
                $graphs = [];
            }

            $vars['graph'] = null;

            $gitem = null;
            $sum_percent = 0;
            foreach ($graphs as $index => $gitem) {
                $sum_percent += $gitem['ПогашениеПроцентов'];
                
                $tds           = null;
                $tds           .= Html::tag('td', $index + 1);
                $tds           .= Html::tag('td', date('Y-m-d', strtotime($gitem['ДатаПлатежа'])));
                $tds           .= Html::tag('td', $gitem['ОстатокОсновногоДолгаНаНачало']);
                $tds           .= Html::tag('td', $gitem['ПогашениеПроцентов']);
                $tds           .= Html::tag('td', $gitem['ПогашениеОсновногоДолга']);
                $tds           .= Html::tag('td', $gitem['ПогашениеПроцентов']);
                $tds           .= Html::tag('td', $gitem['СуммаПлатежа']);
                $vars['graph'] .= Html::tag('tr', $tds);

                $paymentDates[] = date('d-m-Y', strtotime($gitem['ДатаПлатежа'])) . 'г.';
            }
            $vars['graph.count']                       = count($graphs);
            $vars['graph.count_str']                   = num2str($vars['graph.count'], false);
            $vars['graph.payment.sum']                 = ArrayHelper::getValue($graphs, '0.СуммаПлатежа');
            $vars['graph.payment.sum_str']             = num2str($vars['graph.payment.sum']);
            $vars['graph.payment.last_sum']            = ag($gitem, 'СуммаПлатежа');
            $vars['graph.payment.last_date']           = strtotime($gitem['ДатаПлатежа']);
            $vars['graph.payment.last_date.day']       = date('j', $vars['graph.payment.last_date']);
            $vars['graph.payment.last_date.month_str'] = \Yii::$app->formatter->asDate($vars['graph.payment.last_date'], 'MMMM');
            $vars['graph.payment.last_date.year']      = date('Y', $vars['graph.payment.last_date']);
            $vars['graph.payment.last_sum_str']        = num2str($vars['graph.payment.last_sum']);
            $vars['loan.payment.dates']                = implode(', ', $paymentDates);

            $vars['graph.percent']     = round(ag($schedule, 'ПолнаяСтоимостьЗайма'), 3);
            $vars['graph.percent_str'] = float2string($vars['graph.percent']);
            $vars['total_sum']         = ag($schedule, 'ИтогСуммаПлатежа');
            $vars['total_sum_str']     = num2str($vars['total_sum']);
            $vars['sum_percent']       = $sum_percent;
            
            //раздел доп услуг
            if(isset($request->insurance_price) || isset($request->auto_report_price)){
                $price = $request->insurance_price + $request->auto_report_price;
                $vars['dvou.services'] = "<br> - часть суммы Займа, а именно ". num2str($price)." – Займодавец предоставляет Заёмщику в следующем порядке:
                <br> Заключая настоящий Договор, Стороны, руководствуясь ст. 818 Гражданского кодекса Российской Федерации, прекращают денежное обязательство 
                Заёмщика перед Займодавцем, предусмотренное договором возмездного оказания услуг № ".$request->requestUmfo->loan_number." 
                от «".date('j')."» ".\Yii::$app->formatter->asDate(time(), 'MMMM')." ".date('Y')." года в размере ". num2str($price)." , 		
                заменой указанного обязательства обязательством Заёмщика уплатить Займодавцу сумму денежных средств в размере ". num2str($price)." 
                в качестве полученного Заёмщиком от Займодавца займа.</td>";
                
            } else {
                $vars['dvou.services'] = "";
            }
            
        }
        
        if($docId == 4){
            $dvou_n = 1;
            $vars['request.dvou.price'] = 0;
            $vars['request.dvou.price'] += $request->insurance_price + $request->auto_report_price;
            $vars['request.dvou.price_str'] = num2str($vars['request.dvou.price']);
            $vars['dvou.servises'] = '';
            if ($request->insurance == 'Y'){
                $vars['dvou.servises'] .= "1.2.$dvou_n. Услуги  в сфере страхования:<br>
            - предоставление информации и консультация Заказчика по вопросам страхования;<br>
            - по добровольному согласию Заказчика осуществление фактических и юридических действий по заключению договора страхования в отношении Заказчика.<br>";
                $dvou_n++;
            }

            if($request->auto_report == 'Y'){
                $vars['dvou.servises'] .= "1.2.$dvou_n. Однократное предоставление отчета в отношении транспортного средства Заказчика, содержащего в себе данные об истории владения и эксплуатации транспортного средства.<br>";
            }
        }
        if($docId == 11) {
            $vars['early.payment.sum'] = num2str($sum);
        }
        
        if($docId == 12) {
            $contract = Contract::findOne(['request_id' => $request->id]);
            $loanSettlements = LoanSettlements::getLoanSettlements($contract);
            $vars['loan.debt_full'] = intval($loanSettlements['debt_full']) == 0 ?  "_____<s>_</s>_____(______<s>_</s>______)" : num2str($loanSettlements['debt_full']);
            $vars['loan.late_percent'] = intval($loanSettlements['late_percent']) == 0 ? "_____<s>_</s>_____(______<s>_</s>______)" : num2str($loanSettlements['late_percent']);
            $vars['loan.late_main_debt'] = intval($loanSettlements['late_main_debt']) == 0 ? "_____<s>_</s>_____(______<s>_</s>______)" : num2str($loanSettlements['late_main_debt']);
            $penaltes = $loanSettlements['late_penalties'] + $loanSettlements['late_fines'];
            $vars['loan.late_penalties'] = intval($penaltes) == 0 ? "_____<s>_</s>_____(______<s>_</s>______)" : num2str($penaltes);
            $vars['loan.regular_percents'] = intval($loanSettlements['regular_percents']) == 0 ? "_____<s>_</s>_____(______<s>_</s>______)" : num2str($loanSettlements['regular_percents']);
            $vars['loan.regular_main_debt'] = intval($loanSettlements['regular_main_debt']) == 0 ? "_____<s>_</s>_____(______<s>_</s>______)" : num2str($loanSettlements['regular_main_debt']);
            $vars['loan.debt_main'] = intval($loanSettlements['debt_main']) == 0 ? "_____<s>_</s>_____(______<s>_</s>______)" : num2str($loanSettlements['debt_main']);
            $vars['loan.debt_main_percent'] = intval($loanSettlements['debt_main_percent']) == 0 ? "_____<s>_</s>_____(______<s>_</s>______)" : num2str($loanSettlements['debt_main_percent']);
        }
        
        return $vars;
    }
}
