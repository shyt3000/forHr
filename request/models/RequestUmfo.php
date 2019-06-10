<?php

namespace backend\modules\request\models;

use backend\modules\user\models\LoanSettlements;
use backend\modules\request\helper\RequestScheduleHelper;
use backend\modules\user\models\User;
use common\components\UmfoApi;
use common\models\RequestPayment;
use Dadata\Client;
use Yii;
use yii\behaviors\TimestampBehavior;
use admin\components\ApiException;
use backend\modules\request\models\UmfoDoc;
use backend\modules\contract\models\Contract;

/**
 * This is the model class for table "{{%request_umfo}}".
 *
 * @property int     $id
 * @property int     $request_id
 * @property int     $status
 * @property int     $created_at
 * @property int     $updated_at
 * @property string  $client_number
 * @property string  $application_number
 * @property int     $terrorist            ЯвляетсяТеррористом
 * @property string  $terrorist_number     НомерПеречня
 * @property string  $terrorist_date       ДатаПеречня
 * @property int     $terrorist_mvk        РешениеМВК
 * @property string  $terrorist_mvk_date   ДатаРешенияМВК
 * @property string  $terrorist_mvk_number НомерРешенияМВК
 * @property int     $terrorist_fromy      ПереченьФРОМУ
 * @property string  $terrorist_fromy_date ДатаПеречняФРОМУ
 * @property int     $fms                  ДанныеНеОбнаружены
 * @property string  $fssp_task
 * @property int     $fssp_status
 * @property int     $auto_pledge_done
 * @property string  $auto_pledge_records
 * @property int     $client_bankrupt_total_count
 * @property string  $client_bankrupt_records
 * @property string  $loan_number
 * @property string  $loan_date
 * @property string  $deposit_id
 * @property string  $deposit_doc_id
 * @property int     $deposit_doc_status
 * @property string  $deposit_doc_operation
 * @property int     $request_rejected
 *
 * @property Request $request
 */
class RequestUmfo extends \yii\db\ActiveRecord
{

    const STATUS_ACTIVE = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_EROOR = 4;
    const STATUS_TIMEOUT = 3;

    private $_apiUmfo = null;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%request_umfo}}';
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['request_id', 'status'], 'required'],
            [['request_id', 'status', 'terrorist', 'terrorist_mvk', 'terrorist_fromy', 'fms', 'fssp_status', 'auto_pledge_done', 
                'client_bankrupt_total_count', 'request_rejected', 'credit_score_equifax', 'black_list', 'deposit_doc_status'], 'integer'],
            [['client_number', 'application_number', 'terrorist_number', 'terrorist_date', 'fssp_task', 'loan_number', 'loan_date', 'deposit_id', 'deposit_doc_id'], 'string', 'max' => 255],
            [['terrorist_mvk_date', 'terrorist_fromy_date'], 'string', 'max' => 50],
            [['terrorist_mvk_number'], 'string', 'max' => 100],
            [['request_id'], 'exist', 'skipOnError' => true, 'targetClass' => Request::className(), 'targetAttribute' => ['request_id' => 'id']],
            [['auto_pledge_records', 'client_bankrupt_records', 'deposit_doc_operation'], 'string'],
            [['created_at', 'updated_at'], 'safe'],

        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'                   => 'ID',
            'request_id'           => 'Request ID',
            'status'               => 'Status',
            'created_at'           => 'Created At',
            'updated_at'           => 'Updated At',
            'client_number'        => 'Client Number',
            'application_number'   => 'Application Number',
            'terrorist'            => 'ЯвляетсяТеррористом',
            'terrorist_number'     => 'НомерПеречня',
            'terrorist_date'       => 'ДатаПеречня',
            'terrorist_mvk'        => 'РешениеМВК',
            'terrorist_mvk_date'   => 'ДатаРешенияМВК',
            'terrorist_mvk_number' => 'НомерРешенияМВК',
            'terrorist_fromy'      => 'ПереченьФРОМУ',
            'terrorist_fromy_date' => 'ДатаПеречняФРОМУ',
            'fms'                  => 'ДанныеНеОбнаружены',
            'fssp_task'            => 'Fssp Task',
            'fssp_status'          => 'Fssp Status',
            'request_rejected'     => 'Отклонена на этапе скоринга', 
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRequest()
    {
        return $this->hasOne(Request::className(), ['id' => 'request_id']);
    }
    
    public function getContract()
    {
        return $this->hasOne(Contract::className(), ['request_id' => 'request_id']);
    }

    public function getApiUmfo()
    {
        if (!$this->_apiUmfo) {
            $this->_apiUmfo           = new UmfoApi();
            $this->_apiUmfo->login    = 'robot-site';
            $this->_apiUmfo->password = 'robot-site';
        }

        return $this->_apiUmfo;
    }

    public function createClientByRequest(Request $request)
    {
        /*
        $client = new Client(new \GuzzleHttp\Client(), [
            'token' => 'c5f054d34ea39522e2c6d15652ab09474af11291',
            'secret' => '75aed24448a3d221ae345bcb41a352d634ddb25b',
        ]);

        $response = $client->cleanAddress($request->passport_place_birth);

        $place = [
            'НаселенныйПункт' => $response->settlement_with_type,
            'Район'           => $response->area_with_type,
            'Область'         => $response->region_with_type,
            'Страна'          => $response->country,
        ];
 * 
 */

//        $place = [
//            'НаселенныйПункт' => 'ст-ца Каневская',
//            'Район'           => 'Каневской р-н',
//            'Область'         => 'Краснодарский край',
//            'Страна'          => 'Россия',
//        ];

        $data = [
            'ДатаРождения'                  => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Фамилия'                       => $request->lastname,
            'Имя'                           => $request->firstname,
            'Отчество'                      => $request->middlename,
            'Пол'                           => ($request->sex == 'M' ? 'Мужской' : 'Женский'),
            'МестоРождения'                 => $request->passport_place_birth,
            'ИндивидуальныйПредприниматель' => false,
            //            'НачалоРабочегоСтажа'           => '',
            //            'ДатаРегистрации'               => '',
            'ОсновноеОбразование'           => '',
            'ИНН'                           => '',
            'СтраховойНомерПФР'             => '',
            'Паспорт'                       => [
                'Серия' => (string)$request->passport_serial,
                'Номер' => (string)$request->passport_number,
                'ДатаВыдачи' => date('c', strtotime($request->passport_date)),
                'КемВыдан' => $request->passport_place,
                'КодПодразделения' => $request->passport_place_kod,
            ],
            'АдресРегистрации'              => [
                'Страна'          => 'Россия',
                'Индекс'          => $request->reg_index,
                'Регион'          => $request->reg_region,
                'НаселенныйПункт' => $request->reg_city,
                'Улица'           => $request->reg_street,
                'Здание'          => [
                    'ТипЗдания' => 'Дом',
                    'Номер'     => $request->reg_house,
                ],
                'Помещения'       => [
                    [
                        'ТипПомещения' => 'Квартира',
                        'Номер'        => $request->reg_kv
                    ]
                ],
                'Дом'             => $request->reg_house,
                'Корпус'          => $request->reg_korpus,
                'Квартира'        => $request->reg_kv,
            ],
            'АдресПроживания'               => [
                'Страна'          => 'Россия',
                'Индекс'          => $request->life_index,
                'Регион'          => $request->life_region,
                'НаселенныйПункт' => $request->life_city,
                'Улица'           => $request->life_street,
                'Здание'          => [
                    'ТипЗдания' => 'Дом',
                    'Номер'     => $request->life_house,
                ],
                'Помещения'       => [
                    [
                        'ТипПомещения' => 'Квартира',
                        'Номер'        => $request->life_kv,
                    ]
                ],
                'Дом'             => $request->life_house,
                'Корпус'          => $request->life_korpus,
                'Квартира'        => $request->life_kv,
            ],
            //            'АдресМестаРаботы' => [],
            'ТелефонМобильный'              => $request->phone,
            'ТелефонДомашний'               => '',
            'ТелефонРабочий'                => $request->job_phone,
            'Email'                         => $request->email,
            'МестоРаботы'                   => [
                'ТипЗанятости' => $request->unemployed_type > 0 ? 'Безработный' : 'Занятый',
                'Наименование' => $request->job_place,
                'ИНН'          => '',
                'Должность'    => $request->job_position,
                'СтажРаботы'   => $request->job_experience,
                'АдресМестаРоботы' => $request->job_address
            ],
            'ТелефонДоп1'                   => $request->phone_first,
            'ТелефонДоп2'                   => $request->phone_second,
            'ТелефонДоп3'                   => $request->phone_third,
            'КомментарийДопТел1'            => $request->phone_comm_first,
            'КомментарийДопТел2'            => $request->phone_comm_second,
            'КомментарийДопТел3'            => $request->phone_comm_third,
            'Skype'                         => $request->skype,
            "ДополнительныйИсточникДохода"  => $request->additional_income === 'Y',
            "ТекущиеДолговыеОбязательства"  => $request->debts === 'Y',
            "СуммаДолговыхОбязательст"      => $request->debts_sum,
            "ДатаПогашенияОбязательст"      => $request->debts_periodicity ? date('c', strtotime($request->debts_periodicity)) : '',
            "Пенсионер"                     => $request->unemployed_type === 1,
            "НомерПенсионногоУдостоверения" => (string) $request->pensioner_number,
            "мф_ЧасовойПоясПоАдресуПроживания"   => (string) $request->timezone_life,
            "мф_ЧасовойПоясПоАдресуРегистрации"  => (string) $request->timezone_reg,
            "мф_ЧасовойПоясПоМобильномуТелефону" => (string) $request->timezone_phone,
        ];

        $result              = $this->getApiUmfo()->createClient($data);
        $this->client_number = ag($result, 'ИдКонтрагента');
    }

    public function createApplicationByRequest(Request $request)
    {
        $files = [];
        foreach ($request->getFiles() as $requestFile) {
            $files[] = [
                'ТипФайла'   => $requestFile->label,
                'ПутьКФайлу' => $requestFile->path,
            ];
        }
        $data = [
            'ДатаЗаявки'             => stristr(date('c', strtotime($request->date)), '+', true),
            'ДатаРождения'           => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Фамилия'                => $request->lastname,
            'Имя'                    => $request->firstname,
            'Отчество'               => $request->middlename,
            'НомерЗаявки'            => isset($this->application_number) ? $this->application_number : '',
            'ОбеспечениеЗайма'       => [
                'ОбъектЗайма'           => $this->deposit_id,
                'ЗалоговаяСтоимость'    => $request->collateral_price,
                'РыночнаяСтоимость'     => $request->adv_market_price,
                'СправедливаяСтоимость' => '0',
            ],
            'СрокЗайма'              => $request->sum_time,
            'СрокЗаймаПериодичность' => 'МЕСЯЦ',
            'ПрисоединенныеФайлы'    => $files,
            'КодКонтрагента'         => $this->client_number,
            'ФинансовыйПродукт'      => 'Автономи',
            'СуммаЗайма'             => $request->sum,
            "ДопПродуктСтраховка"    =>	$request->auto_report == 'Y',
            "ДопПродуктОтчет"        =>	$request->insurance == 'Y',
            'СпособВыдачи'           => [
                'ПлатежнаяСистема'         => $request->payment_method,
                'РеквизитПлатежнойСистемы' => $request->payment_number,
            ],
            "УникальныйКод" => (string) $request->verify_code,
        ];
       
        $result                   = $this->getApiUmfo()->createApplication($data);
        $this->application_number = ag($result, 'Номер');
    }

    public function checkClientTerroristByRequest(Request $request)
    {
        $data = [
            'Фамилия'      => $request->lastname,
            'Имя'          => $request->firstname,
            'Отчество'     => $request->middlename,
            'ДатаРождения' => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Серия'        => (string)$request->passport_serial,
            'Номер'        => (string)$request->passport_number,
            'НомерЗаявки'  => $this->application_number,
            'ДатаЗаявки'   => stristr(date('c', strtotime($request->date)), '+', true),
        ];

        $result = $this->getApiUmfo()->checkClientTerrorist($data);

        $this->terrorist            = (int)ag($result, 'ЯвляетсяТеррористом');
        $this->terrorist_date       = ag($result, 'ДатаПеречня');
        $this->terrorist_number     = ag($result, 'НомерПеречня');
        $this->request_rejected     = (int)ag($result, 'ОтклонитьЗаявку');
       // return $result;
    }

    public function checkClientFROMUByRequest(Request $request)
    {
        $data = [
            'Фамилия'      => $request->lastname,
            'Имя'          => $request->firstname,
            'Отчество'     => $request->middlename,
            'ДатаРождения' => stristr(date('c', strtotime($request->birthday)), '+', true),
            'НомерЗаявки'  => $this->application_number,
            'ДатаЗаявки'   => stristr(date('c', strtotime($request->date)), '+', true),
        ];

        $result = $this->getApiUmfo()->checkClientFROMU($data);

        $this->terrorist_fromy      = (int)ag($result, 'ПереченьФРОМУ');
        $this->terrorist_fromy_date = ag($result, 'ДатаПеречняФРОМУ');
        $this->request_rejected     = (int)ag($result, 'ОтклонитьЗаявку');
    }
    
    public function checkClientCreditScoreEquiFaxByRequest(Request $request)
    {
        $data = [
            'НомерЗаявки'   => $this->application_number,
            'ДатаЗаявки'    => stristr(date('c', strtotime($request->date)), '+', true),
            'ДатаРождения'  => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Фамилия'       => $request->lastname,
            'Имя'           => $request->firstname,
            'Отчество'      => $request->middlename,
        ];
        
        $result = $this->getApiUmfo()->checkClientCreditScoreEquiFax($data);
        $this->request_rejected     = (int)ag($result, 'ОтклонитьЗаявку');
        $this->credit_score_equifax = (int)ag($result, 'БаллКредитнойИстории');
    }

    public function checkClientBlockedListByRequest(Request $request)
    {
        $data = [
            'Фамилия'      => $request->lastname,
            'Имя'          => $request->firstname,
            'Отчество'     => $request->middlename,
            'ДатаРождения' => stristr(date('c', strtotime($request->birthday)), '+', true),
            'НомерЗаявки'  => $this->application_number,
            'ДатаЗаявки'   => stristr(date('c', strtotime($request->date)), '+', true),
        ];

        $result = $this->getApiUmfo()->checkClientBlockedList($data);

        $this->terrorist_mvk        = (int)ag($result, 'РешениеМВК');
        $this->terrorist_mvk_number = ag($result, 'НомерРешенияМВК');
        $this->terrorist_mvk_date   = ag($result, 'ДатаРешенияМВК');
        $this->request_rejected     = (int)ag($result, 'ОтклонитьЗаявку');
    }
    
    public function checkBlacklistMFByRequest(Request $request)
    {
        $data = [
            'Фамилия'       => $request->lastname,
            'Имя'           => $request->firstname,
            'Отчество'      => $request->middlename,
            'ДатаРождения'  => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Серия'         => $request->passport_serial,
            'Номер'         => $request->passport_number,
            'НомерЗаявки'   => $this->application_number,
            'ДатаЗаявки'    => stristr(date('c', strtotime($request->date)), '+', true),
        ];
        
        $result = $this->getApiUmfo()->checkBlacklistMF($data);
        
        $this->black_list           = (int)ag($result, 'ВЧерномСписке');
        $this->request_rejected     = (int)ag($result, 'ОтклонитьЗаявку');
 
    }

    public function checkClientFMSByRequest(Request $request)
    {
        $data = [
            'Фамилия'      => $request->lastname,
            'Имя'          => $request->firstname,
            'Отчество'     => $request->middlename,
            'ДатаРождения' => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Серия'        => (string)$request->passport_serial,
            'Номер'        => (string)$request->passport_number,
            'НомерЗаявки'  => $this->application_number,
            'ДатаЗаявки'   => stristr(date('c', strtotime($request->date)), '+', true),
        ];

        //        echo $this->getApiUmfo()->url;
        //        dump($data);
        $result = $this->getApiUmfo()->checkClientFMS($data);

        $this->fms = (int)ag($result, 'ДанныеНеОбнаружены');
        return $this->request_rejected     = (int)ag($result, 'ОтклонитьЗаявку');
    }

    public function checkClientFSSPByRequest(Request $request)
    {
        $data = [
            'Фамилия'      => $request->lastname,
            'Имя'          => $request->firstname,
            'Отчество'     => $request->middlename,
            'ДатаРождения' => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Серия'        => (string)$request->passport_serial,
            'Номер'        => (string)$request->passport_number,
            'НомерЗаявки'  => $this->application_number,
            'ДатаЗаявки'   => stristr(date('c', strtotime($request->date)), '+', true),
        ];

        $result            = $this->getApiUmfo()->checkClientFSSP($data);
        $task              = $result['task'];
        $this->fssp_status = 2;
        $this->fssp_task   = $task;
    }


    public function checkBlackListRegionByRequest(Request $request)
    {
        $data = [
            'Фамилия'             => $request->lastname,
            'Имя'                 => $request->firstname,
            'Отчество'            => $request->middlename,
            'НаименованиеРегиона' => 'Новосибирская область',
            'НомерЗаявки'         => $this->application_number,
            'ДатаЗаявки'          => stristr(date('c', strtotime($request->date)), '+', true),
        ];

        $result                  = $this->getApiUmfo()->checkBlackListRegion($data);
        $strBool                 = ag($result, 'ВходитВСписокРегионовЧС');
        $this->region_black_list = (int)($strBool == 'true');
        $this->request_rejected  = (int)ag($result, 'ОтклонитьЗаявку');
    }

    public function checkPassportExpiredByRequest(Request $request)
    {
        $data = [
            'Фамилия'            => $request->lastname,
            'Имя'                => $request->firstname,
            'Отчество'           => $request->middlename,
            'ДатаРождения'       => stristr(date('c', strtotime($request->birthday)), '+', true),
            'ДатаВыдачиПаспорта' => stristr(date('c', strtotime($request->passport_date)), '+', true),
            'НомерЗаявки'        => $this->application_number,
            'ДатаЗаявки'         => stristr(date('c', strtotime($request->date)), '+', true),
        ];

        $result                 = $this->getApiUmfo()->checkPassportExpired($data);
        $strBool                = ag($result, 'ПаспортПросрочен');
        $this->passport_expired = (int)($strBool == 'true');
        $this->request_rejected = (int)ag($result, 'ОтклонитьЗаявку');
    }


    public function checkClientAgeByRequest(Request $request)
    {
        $data = [
            'Фамилия'      => $request->lastname,
            'Имя'          => $request->firstname,
            'Отчество'     => $request->middlename,
            'ДатаРождения' => stristr(date('c', strtotime($request->birthday)), '+', true),
            'НомерЗаявки'  => $this->application_number,
            'ДатаЗаявки'   => stristr(date('c', strtotime($request->date)), '+', true),
        ];

        $result                 = $this->getApiUmfo()->checkClientAge($data);
        $strBool                = ag($result, 'ПроверкаПройдена');
        $this->check_client_age = (int)($strBool == 'true');
        $this->request_rejected = (int)ag($result, 'ОтклонитьЗаявку');
    }

    public function checkClientListRefusersByRequest(Request $request)
    {
        $data = [
            'Фамилия'      => $request->lastname,
            'Имя'          => $request->firstname,
            'Отчество'     => $request->middlename,
            'ДатаРождения' => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Серия'        => (string)$request->passport_serial,
            'Номер'        => (string)$request->passport_number,
            'НомерЗаявки'  => $this->application_number,
            'ДатаЗаявки'   => stristr(date('c', strtotime($request->date)), '+', true),
        ];

        $result                           = $this->getApiUmfo()->checkClientListRefusers($data);
        $strBool                          = ag($result, 'ЯвляетсяОтказником');
        $this->check_client_list_refusers = (int)($strBool == 'true');
        $this->request_rejected = (int)ag($result, 'ОтклонитьЗаявку');
    }

    public function checkAvtoVINRZByRequest(Request $request)
    {
        $data = [
            'Фамилия'      => $request->lastname,
            'Имя'          => $request->firstname,
            'Отчество'     => $request->middlename,
            'ДатаРождения' => stristr(date('c', strtotime($request->birthday)), '+', true),
            'VIN'          => $request->car_pts_vin,
            'НомерЗаявки'  => $this->application_number,
            'ДатаЗаявки'   => stristr(date('c', strtotime($request->date)), '+', true),
        ];

        $result                    = $this->getApiUmfo()->checkAvtoVINRZ($data);
        $this->auto_pledge_done    = (int)ag($result, 'reestr_done');
        $this->auto_pledge_records = ag($result, 'records');
    }

    public function checkClientBankruptByRequest(Request $request)
    {
        $data = [
            'Фамилия'      => $request->lastname,
            'Имя'          => $request->firstname,
            'Отчество'     => $request->middlename,
            'ДатаРождения' => stristr(date('c', strtotime($request->birthday)), '+', true),
            'НомерЗаявки'  => $this->application_number,
            'ДатаЗаявки'   => stristr(date('c', strtotime($request->date)), '+', true),
            'СНИЛС'        => $request->snils,
        ];

        $result                            = $this->getApiUmfo()->checkClientBankrupt($data);
        $this->client_bankrupt_total_count = (int)ag($result, 'total_count');
        $this->client_bankrupt_records     = json_encode(ag($result, 'records'));
        $this->request_rejected            = (int)ag($result, 'ОтклонитьЗаявку');
    }

    public function createLoanByRequest(Request $request)
    {
        $data = [
            'НомерЗаявки' => $this->application_number,
            'ДатаЗаявки'  => stristr(date('c', strtotime($request->date)), '+', true),
            'ДатаЗайма'   => stristr(date('c'), '+', true),
        ];

        $result            = $this->getApiUmfo()->createLoan($data);
        $this->loan_number = ag($result, 'Номер');
        $this->loan_date   = ag($result, 'Дата');

        return $this->save();
    }

    public function createDepositByRequest(Request $request)
    {
        $data = [
            'ДатаРождения'         => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Фамилия'              => $request->lastname,
            'Имя'                  => $request->firstname,
            'Отчество'             => $request->middlename,
            'ТранспортноеСредство' => [
                'Наименование'                  => $request->car_pts_brand . ' ' . $request->car_pts_model,
                'ДополнительноеОписание'        => $request->car_pts_brand . ' ' . $request->car_pts_model,
                'АдресМестаНахождения'          => '',
                'ГодВыпуска'                    => $request->car_pts_create_year,
                'ГосударственныйНомернойЗнак'   => $request->car_gos_number,
                'ДатаРегистрации'               => '1970-01-01T00:00:00',
                'ДополнительнаяХарактеристика'  => $request->car_pts_special,
                'МаксимальнаяМасса'             => '',
                'Марка'                         => (string)$request->car_pts_brand,
                'МассаБезНагрузки'              => '',
                'МощностьДвигателя'             => $request->car_pts_power,
                'МощностьДвигателяЛС'           => $request->car_pts_power,
                'НомерVin'                      => $request->car_pts_vin,
                'НомерДвигателя'                => '',
                'НомерКузова'                   => '',
                'НомерШасси'                    => '',
                'ОбъемДвигателя'                => '',
                'Пробег'                        => $request->car_sts_mileage,
                'ПТСДатаВыдачи'                 => date("Y-m-d\TH:i:s", strtotime($request->car_pts_date)),
                'ПТСКемВыдан'                   => $request->car_pts_place,
                'ПТСНомер'                      => $request->car_pts_number,
                'ПТССерия'                      => $request->car_pts_serial,
                'СТСКемВыдан'                   => $request->car_sts_place,
                'СТСНомер'                      => $request->car_sts_number,
                'СТССерия'                      => $request->car_sts_serial,
                'СТСДатаВыдачи'                 => date("Y-m-d\TH:i:s", strtotime($request->car_sts_date)),
                'СтранаПроизводитель'           => '',
                'ТипДвигателя'                  => '',
                'ЦветКузова'                    => $request->car_pts_color,
                'КатегорияТС'                   => $request->car_pts_category,
                'ТипТС'                         => $request->car_sts_type,
                'ОсобыеОтметки'                 => $request->car_pts_special,
                'ПоврежденияТС'                 => $request->car_sts_crash,
            ]
        ];

        $result           = $this->getApiUmfo()->createDeposit($data);
        $this->deposit_id = ag($result, 'ИдЗалога');
    }

    public function createSchedule()
    {
        $data = [
            'ДатаЗайма'   => $this->loan_date,
            'ДатаГрафика' => stristr(date('c'), '+', true),
            'НомерЗайма'  => $this->loan_number,
        ];

        $result = $this->getApiUmfo()->createSchedule($data);

        RequestScheduleHelper::createRequestSchedule($this->request_id, $result);

        return $result;
    }

    public function createDocDepositByRequest(Request $request)
    {
        $data = [
            'Дата'  => $this->loan_date,
            'Номер' => $this->loan_number,
            'Регион' => $request->reg_region,
            'Город' => $request->reg_city,
            'Улица' => $request->reg_street,
            'Дом' => $request->reg_house,
            'Строение' => $request->reg_building,
            'Квартира' => $request->reg_kv,
        ];

        $result                = $this->getApiUmfo()->createDocDeposit($data);
        $this->deposit_doc_id  = ag($result, 'ИдЗалога');
        
        if(!$this->save()){
            throw new ApiException($this->getFirstErrors());
        }
        
        return $this->save();
    }
    
    public function createDocDispensingMoney($user, $requestPaymentId)
    {
        $data = [
            'Операция' => 'Создание документа',
            'НомерЗайма' => $this->loan_number,
            'ДатаЗайма' => $this->loan_date,
            'НомерВыдачи' => '',
            'ДатаВыдачи' => date("Y-m-d\TH:i:s"),
            'Фамилия' => $user->lastname,
            'Имя' => $user->firstname,
            'Отчество' => $user->middlename,
            'ДатаРождения' => $user->birthday,
            'ДатаОплаты' => date("Y-m-d\TH:i:s"),
            'Комментарий' => '',
        ];
      //  return print_r(json_encode($data));
        $result = $this->getApiUmfo()->editDocDispensingMoney($data);
        $request_payment = RequestPayment::findOne($requestPaymentId);
        if(!$request_payment){
            throw new ApiException('не найдена заявка на выплату');
        }
      //  return print_r($result);
        $request_payment->issue_number = ag($result, 'НомерВыдачи');
        $request_payment->issue_date   = ag($result, 'ДатаВыдачи');
        
        if(!$request_payment->save()){
            throw new ApiException($request_payment->getFirstErrors());
        } else {
            return true;
        }
        
        
    }

    public function editDocDispensingMoney($user, $status, $request_payment)
    {
        $comment = '';
        switch ($status){
            
            case 'error':
                $operation = 'Установить статус ошибка перечисления';
                $comment = $request_payment->error_info;
                break;
            
            case 'done':
                $operation = 'Установить статус успешной выдачи';
                break;
            
            case 'expired':
                $operation = 'Установить статус истечения срока выдачи';
                $comment = 'истек срок перечисления';
                break;
            
            default :
                throw new ApiException('не верный статус');
            
        }
        $data = [
            'Операция' => $operation,
            'НомерЗайма' => $this->loan_number,
            'ДатаЗайма' => $this->loan_date,
            'НомерВыдачи' => $request_payment->issue_number,
            'ДатаВыдачи' => $request_payment->issue_date,
            'Фамилия' => $user->lastname,
            'Имя' => $user->firstname,
            'Отчество' => $user->middlename,
            'ДатаРождения' => $user->birthday,
            'ДатаОплаты' => $request_payment->payment_date,
            'Комментарий' => $comment,
        ];
      //  return print_r(json_encode($data));
        $result = $this->getApiUmfo()->editDocDispensingMoney($data);
        $request_payment->issue_number = ag($result, 'НомерВыдачи');
        $request_payment->issue_date   = ag($result, 'ДатаВыдачи');
        
        if(!$request_payment->save()){
            throw new ApiException($request_payment->getFirstErrors());
        } else {
            return true;
        }
        
    }

    public function checkClientExistsByUser(User $user)
    {
        $data = [
            'Фамилия'      => $user->lastname,
            'Имя'          => $user->firstname,
            'Отчество'     => $user->middlename,
            'ДатаРождения' => stristr(date('c', strtotime($user->birthday)), '+', true),
            'Телефон'      => $user->phone,
        ];

        $result = $this->getApiUmfo()->checkClientExists($data);

        return ag($result, 'КонтрагентНайден');
    }
    
    public function activeLoansMutualSettlementsByRequest(Request $request)
    {
        $data = [
            "Фамилия"       => $request->lastname,
            "Имя"           => $request->firstname,
            "Отчество"      => $request->middlename,
            "ДатаРождения"  => stristr(date('c', strtotime($request->birthday)), '+', true),
            "Серия"         => (string)$request->passport_serial,
            "Номер"         => (string)$request->passport_number
        ];
        
        return $this->getApiUmfo()->activeLoansMutualSettlements($data);
    }
    
    public function createAReportAutokodByRequest(Request $request)
    {
        $data = [
            'Фамилия'       => $request->lastname,
            'Имя'           => $request->firstname,
            'Отчество'      => $request->middlename,
            'ДатаРождения'  => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Серия'         => (string)$request->passport_serial,
            'Номер'         => (string)$request->passport_number,
            'НомерЗайма'    => $this->loan_number,
            'СредняяСтоимость' => $request->adv_market_price
        ];
        
        $result = $this->getApiUmfo()->createAReportAutokod($data);
        
       // return $result;
        
        $model = new UmfoDoc();
        $model->request_id = $request->id;
        $model->label = $model::LABEL_AUTO;
        $model->type = $model::TYPE_AUTO;
        $model->date = date("Y-m-d H:i:s");
        $model->path = ag($result, 'ПутьКФайлу');
        if(!$model->validate()){
            throw new ApiException($model->getFirstErrors());
        }
        if(!$model->save()){
            throw new ApiException($model->getFirstErrors());
        }
        return $model->id;
    }
    
    public function createInsuranceRenessansByRequest(Request $request)
    {
        $data = [
            'Фамилия'       => $request->lastname,
            'Имя'           => $request->firstname,
            'Отчество'      => $request->middlename,
            'ДатаРождения'  => stristr(date('c', strtotime($request->birthday)), '+', true),
            'Серия'         => (string)$request->passport_serial,
            'Номер'         => (string)$request->passport_number,
            'НомерЗайма'    => $this->loan_number
        ];
        
        $result = $this->getApiUmfo()->createInsuranceRenessans($data);
        
      //  return $result;
        
        $model = new UmfoDoc();
        $model->request_id = $request->id;
        $model->label = $model::LABEL_INSURANSE;
        $model->type = $model::TYPE_INSURANSE;
        $model->date = date("Y-m-d H:i:s");
        $model->path = ag($result, 'ПутьКФайлу');
        if(!$model->validate()){
            throw new ApiException($model->getFirstErrors());
        }
        if(!$model->save()){
            throw new ApiException($model->getFirstErrors());
        }
        return $model;
    }

    public function createPaymentDocument($amount, $depositId)
    {
        $data = [
            'НомерЗайма'   => $this->loan_number,
            'СуммаПлатежа' => $amount,
            'ИдентификаторТранзакции' => $depositId,
        ];
        LoanSettlements::clearDate($this);
        $result = $this->getApiUmfo()->createPaymentDocument($data);

        return ag($result, 'НомерДокументаОплаты');
    }
}
