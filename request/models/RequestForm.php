<?php

namespace backend\modules\request\models;

use backend\components\ApiException;
use backend\components\PhoneValidator;
use backend\modules\request\jobs\PrescoringJob;
use backend\modules\request\jobs\PriceParserJob;
use backend\modules\request\jobs\AutoParsingCheckJob;
use backend\modules\user\models\User;
use backend\modules\user\models\UserProfileForm;
use common\models\UserAction;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use backend\modules\user\models\UserProfile;
use backend\modules\request\components\{AutoMamaParser, AutoRuParser, DromParser};
use common\helpers\DadataHelper;
use yii\queue\db\Queue;

class RequestForm extends Model
{
    public $sum;
    public $sum_time;
    public $phone;
    public $email;
    public $sex;
    public $email_distribution;
    public $lastname;
    public $firstname;
    public $middlename;
    public $birthday;
    public $personal_agreement;
    public $passport_serial;
    public $passport_number;
    public $passport_place;
    public $passport_date;
    public $passport_place_birth;
    public $passport_place_kod;
    public $reg_index;
    public $reg_region;
    public $reg_city;
    public $reg_street;
    public $reg_house;
    public $reg_korpus;
    public $reg_kv;
    public $reg_building;
    public $life_index;
    public $life_region;
    public $life_city;
    public $life_street;
    public $life_house;
    public $life_korpus;
    public $life_kv;
    public $life_building;
    public $skype;
    public $phone_first;
    public $phone_comm_first;
    public $phone_second;
    public $phone_comm_second;
    public $phone_third;
    public $phone_comm_third;
    public $income_month;
    public $unemployed_type;
    public $pensioner_number;
    public $job_place;
    public $job_address;
    public $job_phone;
    public $job_position;
    public $job_experience;
    public $car_pts_number;
    public $car_pts_vin;
    public $car_pts_model;
    public $car_pts_brand;
    public $car_pts_category;
    public $car_pts_create_year;
    public $car_pts_color;
    public $car_pts_power;
    public $car_pts_special;
    public $car_pts_date;
    public $car_pts_place;
    public $car_sts_number;
    public $car_sts_vin;
    public $car_sts_serial;
    public $car_pts_serial;
    public $car_sts_model;
    public $car_sts_brand;
    public $car_sts_type;
    public $car_sts_create_year;
    public $car_sts_color;
    public $car_sts_mileage;
    public $car_sts_crash;
    public $car_sts_kp;
    public $car_sts_date;
    public $car_sts_place;
    public $payment_method;
    public $payment_number;
    public $verify_code;
    public $insurance;
    public $action;
    public $utm;
    public $step;
    public $avg_auto_mama;
    public $avg_auto_ru;
    public $avg_drom_ru;
    public $additional_income;
    public $debts;
    public $debts_sum;
    public $debts_periodicity;
    public $job_inn;
    public $employment_type;
    public $snils;
    public $car_gos_number;
    //Галки согласия
    public $legal_family;
    public $legal_colector;
    public $legal_v;
    public $legal_asp;
    public $legal_agreement;
    public $legal_breach;
    public $legal_ad;
    public $legal_accept;
    public $auto_report;

    private $_request;
    private $_userId;
    private $_profile;
    private $_user;

    const SCENARIO_SAVE_DRAFT = 'save_draft';

    public function rules()
    {
        return [
            [['phone', 'email', 'sex', 'personal_agreement'], 'required', 'except' => self::SCENARIO_SAVE_DRAFT],
            #Информация о собственнике автомобиля
            [['firstname', 'lastname', 'birthday'], 'required', 'except' => self::SCENARIO_SAVE_DRAFT],
            #Паспортные данные
            [['passport_number', 'passport_serial', 'passport_place', 'passport_date', 'passport_place_birth', 'passport_place_kod'], 'required', 'except' => self::SCENARIO_SAVE_DRAFT],
            #Адрес по прописке
            [['reg_region', 'reg_city', 'reg_street', 'reg_house', 'reg_index'], 'required', 'except' => self::SCENARIO_SAVE_DRAFT],
            #Адрес проживания
            [['life_region', 'life_city', 'life_street', 'life_house', 'life_house'], 'required', 'except' => self::SCENARIO_SAVE_DRAFT],
            #Контактные данные
            [['phone_first', 'phone_second', 'phone_third', 'income_month'], 'required', 'except' => self::SCENARIO_SAVE_DRAFT],
            #Сведения о работе
            [
                'pensioner_number',
                'required',
                'when'   => function ($model) {
                    return ($model->unemployed_type == 1);
                },
                'except' => self::SCENARIO_SAVE_DRAFT,
            ],
            [
                ['job_place', 'job_address', 'job_phone'],
                'required',
                'when'   => function ($model) {
                    return (!$model->unemployed_type);
                },
                'except' => self::SCENARIO_SAVE_DRAFT,
            ],
            #Паспорт транспортного средства
            [['car_pts_number', 'car_pts_vin', 'car_pts_model', 'car_sts_brand', 'car_pts_category', 'car_pts_create_year', 'car_pts_color', 'car_pts_power', 'car_pts_date'], 'required', 'except' => self::SCENARIO_SAVE_DRAFT],
            #Свидетельство о регистрации транспортного средства
            [['car_sts_number', 'car_sts_vin', 'car_sts_model', 'car_sts_brand', 'car_sts_type', 'car_sts_create_year', 'car_sts_color', 'car_sts_mileage', 'car_gos_number', 'car_sts_date'], 'required', 'except' => self::SCENARIO_SAVE_DRAFT],
            #Выбор платежной системы
            [['payment_method', 'payment_number'], 'required', 'except' => self::SCENARIO_SAVE_DRAFT],
            ['email', 'email'],
            ['sex', 'in', 'range' => ['M', 'W']],
            [['birthday', 'passport_date', 'car_sts_date', 'car_pts_date'], 'safe'],
            [['sum_time', 'sum'], 'integer'],
            [['car_pts_create_year', 'car_pts_power', 'car_sts_create_year'], 'integer', 'min' => 1, 'max' => 9999],
            ['car_sts_mileage', 'integer', 'min' => 1, 'max' => 9999999999],
            [['email_distribution', 'insurance', 'auto_report', 'additional_income', 'debts'], 'in', 'range' => ['Y', 'N']],
            [['sex', 'email_distribution'], 'string'],
            [['phone_first', 'phone_second', 'phone_third', 'job_phone'], 'string'],
            [['income_month'], 'number', 'min' => 1, 'max' => 99999999],
            [['debts_sum'], 'number', 'min' => 1, 'max' => 99999999],
            [['debts_periodicity'], 'safe'],
            [['email'], 'string', 'max' => 100],
            [['lastname', 'firstname', 'middlename', 'passport_place', 'passport_place_birth', 'car_pts_special', 'car_sts_crash'], 'string', 'max' => 200],
            [
                [
                    'snils',
                    'skype',
                    'car_pts_vin',
                    'car_pts_model',
                    'car_pts_brand',
                    'car_pts_color',
                    'car_sts_vin',
                    'car_sts_model',
                    'car_sts_brand',
                    'car_sts_color',
                    'payment_method',
                    'payment_number',
                    'job_inn',
                ],
                'string',
                'max' => 20,
            ],
            [['reg_city', 'reg_street', 'life_city', 'life_street',], 'string', 'max' => 200],
            [['reg_index', 'life_index'], 'string', 'max' => 8],
            [['reg_building', 'life_building'], 'string', 'max' => 30],
            [['job_place'], 'string', 'max' => 200],
            [['passport_serial', 'car_sts_serial', 'car_pts_serial'], 'string', 'min' => 4, 'max' => 4],
            [['passport_number', 'car_sts_number', 'car_pts_number'], 'string', 'min' => 6, 'max' => 6],
            [['car_gos_number'], 'string', 'max' => 10],
            [['job_experience'], 'string', 'max' => 50],
            [['job_experience'], 'in', 'range' => array_keys(Request::enumExperience())],
            [['job_address'], 'string', 'max' => 250],
            [['passport_place_kod', 'car_pts_category', 'car_sts_type', 'car_sts_kp'], 'string', 'max' => 10],
            [['reg_region', 'life_region'], 'string', 'max' => 100],
            [['phone_comm_first', 'phone_comm_second', 'phone_comm_third', 'pensioner_number', 'job_place', 'job_address'], 'string', 'max' => 50],
            [['reg_house', 'life_house'], 'string', 'max' => 100],
            [['reg_korpus', 'reg_kv', 'life_korpus', 'life_kv'], 'string', 'max' => 5],
            [['job_position'], 'string', 'max' => 200],
            [['phone'], PhoneValidator::class],
            ['action', 'integer'],
            ['employment_type', 'integer'],
            ['unemployed_type', 'integer'],
            ['step', 'integer'],
            ['utm', 'string', 'max' => 60],
            [['legal_family', 'legal_colector', 'legal_v', 'legal_asp', 'legal_agreement', 'legal_breach', 'legal_ad', 'legal_accept'], 'required', 'except' => self::SCENARIO_SAVE_DRAFT],
            [['legal_family', 'legal_colector', 'legal_v', 'legal_asp', 'legal_agreement', 'legal_breach', 'legal_ad', 'legal_accept'], 'compare', 'compareValue' => 'Y', 'operator' => '=='],
            ['passport_place_kod', 'match', 'pattern' => '/^\d{3}\-\d{3}/', 'message' => 'Код подразделения указан не верно. Ожидается формат 123-123'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            throw new ApiException($this->getFirstErrors());
        }

        $request = $this->getRequest();
        if ($this->scenario != self::SCENARIO_SAVE_DRAFT) {
            $this->fileValidate($request);
        }
        if ($request->isNewRecord) {
            $request->user_id = $this->getUserId();
        } elseif ($request->user_id != $this->getUserId()) {
            throw new ApiException('Заявка недоступна этому пользователю');
        }
        if ($this->scenario == self::SCENARIO_SAVE_DRAFT) {
            $request->type = Request::TYPE_DRAFT;
        } else {
            $request->type = Request::TYPE_ACTIVE;
            $request->date = date('Y-m-d H:i:s');
        }

        $request = $this->getRequestTimezone($request);

        $request->sum                  = $this->sum;
        $request->sum_time             = $this->sum_time;
        $request->phone                = $this->phone;
        $request->email                = $this->email;
        $request->sex                  = $this->sex;
        $request->email_distribution   = $this->email_distribution;
        $request->lastname             = $this->lastname;
        $request->firstname            = $this->firstname;
        $request->middlename           = $this->middlename;
        if(isset($this->birthday))
            $request->birthday             = date('Y-m-d', strtotime($this->birthday));
        $request->passport_number      = $this->passport_number;
        $request->passport_serial      = $this->passport_serial;
        $request->passport_place       = $this->passport_place;
        if(isset($this->passport_date))
            $request->passport_date        = date('Y-m-d', strtotime($this->passport_date));
        $request->passport_place_birth = $this->passport_place_birth;
        $request->passport_place_kod   = $this->passport_place_kod;
        $request->life_index           = $this->life_index;
        $request->life_building        = $this->life_building;
        $request->reg_index            = $this->reg_index;
        $request->reg_building         = $this->reg_building;
        $request->reg_region           = $this->reg_region;
        $request->reg_city             = $this->reg_city;
        $request->reg_street           = $this->reg_street;
        $request->reg_house            = $this->reg_house;
        $request->reg_korpus           = $this->reg_korpus;
        $request->reg_kv               = $this->reg_kv;
        $request->life_region          = $this->life_region;
        $request->life_city            = $this->life_city;
        $request->life_street          = $this->life_street;
        $request->life_house           = $this->life_house;
        $request->life_korpus          = $this->life_korpus;
        $request->life_kv              = $this->life_kv;
        $request->skype                = $this->skype;
        $request->phone_first          = $this->phone_first;
        $request->phone_comm_first     = $this->phone_comm_first;
        $request->phone_second         = $this->phone_second;
        $request->phone_comm_second    = $this->phone_comm_second;
        $request->phone_third          = $this->phone_third;
        $request->phone_comm_third     = $this->phone_comm_third;
        $request->income_month         = $this->income_month;
        $request->unemployed_type      = $this->unemployed_type;
        $request->pensioner_number     = $this->pensioner_number;
        $request->job_place            = $this->job_place;
        $request->job_address          = $this->job_address;
        $request->job_phone            = $this->job_phone;
        $request->job_position         = $this->job_position;
        $request->job_experience       = $this->job_experience;
        $request->car_pts_number       = $this->car_pts_number;
        $request->car_sts_serial       = $this->car_sts_serial;
        $request->car_pts_serial       = $this->car_pts_serial;
        $request->car_pts_vin          = $this->car_pts_vin;
        $request->car_pts_model        = $this->car_pts_model;
        $request->car_pts_brand        = $this->car_pts_brand;
        $request->car_pts_category     = $this->car_pts_category;
        $request->car_pts_create_year  = $this->car_pts_create_year;
        $request->car_pts_color        = $this->car_pts_color;
        $request->car_pts_power        = $this->car_pts_power;
        $request->car_pts_special      = $this->car_pts_special;
        if(isset($this->car_pts_date))
            $request->car_pts_date         = date('Y-m-d', strtotime($this->car_pts_date));
        $request->car_sts_number       = $this->car_sts_number;
        $request->car_sts_vin          = $this->car_sts_vin;
        $request->car_sts_model        = $this->car_sts_model;
        $request->car_sts_brand        = $this->car_sts_brand;
        $request->car_sts_type         = $this->car_sts_type;
        $request->car_sts_create_year  = $this->car_sts_create_year;
        $request->car_sts_color        = $this->car_sts_color;
        $request->car_sts_mileage      = $this->car_sts_mileage;
        $request->car_sts_crash        = $this->car_sts_crash;
        $request->car_sts_kp           = $this->car_sts_kp;
        if(isset($this->car_sts_date))
            $request->car_sts_date         = date('Y-m-d', strtotime($this->car_sts_date));
        $request->payment_method       = $this->payment_method;
        $request->payment_number       = $this->payment_number;
        $request->insurance            = $this->insurance;
        $request->auto_report          = $this->auto_report;
        $request->action               = $this->action;
        $request->utm                  = $this->utm;
        $request->step                 = $this->step;
        $request->additional_income    = $this->additional_income;
        $request->debts                = $this->debts;
        $request->debts_periodicity    = date('Y-m-d', strtotime($this->debts_periodicity));
        $request->debts_sum            = $this->debts_sum;
        $request->job_inn              = $this->job_inn;
        $request->employment_type      = $this->employment_type;
        $request->car_gos_number       = $this->car_gos_number;
        $request->snils                = $this->snils;
        $request->date_update          = date('Y-m-d H:i:s');

        $saveAttributes = null;
        if ($this->scenario == self::SCENARIO_SAVE_DRAFT) {
            $saveAttributes = array_keys(array_filter($request->attributes));
        }
        if ($this->scenario != self::SCENARIO_SAVE_DRAFT) {
            $profile = $this->getProfile();
            $user    = $this->getUser();
            foreach ($profile->getAttributes() as $attribute => $value) {
                if (property_exists($this, $attribute) && $this->{$attribute}) {
                    $profile->{$attribute} = $this->{$attribute};
                }
            }
            foreach ($user->getAttributes() as $attribute => $value) {
                if (property_exists($this, $attribute) && $this->{$attribute}) {
                    $user->{$attribute} = $this->{$attribute};
                }
            }
            $profile->user_id       = user()->id;
            $profile->passport_date = date('Y-m-d', strtotime($this->passport_date));
            $profile->timezone_reg   = $request->timezone_reg;
            $profile->timezone_life  = $request->timezone_life;
            $profile->timezone_phone = $request->timezone_phone;

            $user->birthday = date('Y-m-d', strtotime($this->birthday));

            if (!$profile->save(true) || !$user->save(true)) {
                throw new ApiException($profile->getFirstErrors());
            }
        }

        if (!$request->save(true, $saveAttributes)) {
            throw new ApiException($request->getFirstErrors());
            //throw new ApiException('Системная ошибка');
        } else {
            if ($this->scenario != self::SCENARIO_SAVE_DRAFT) {
                // Клаем задание на парсинг в очередь
                $this->enqueuePriceParser($request);
                Yii::$app->queuePrescoring->push(new PrescoringJob(['request' => $request]));
                UserAction::add(UserAction::ACTION_REQUEST_AGREE, $request->id, null, $this->getUserId());
            }
        }

        return $request;
    }

    /**
     * @param Request $request
     */
    private function enqueuePriceParser(Request $request)
    {
        $queueDromId = Yii::$app->queueDrom->push(new PriceParserJob([
            'request_attribute' => 'avg_drom_ru',
            'request' => $request,
            'parser' => DromParser::class,

        ]));
        $queueAutoMamaId = Yii::$app->queueAutoMama->push(new PriceParserJob([
            'request_attribute' => 'avg_auto_mama',
            'request' => $request,
            'parser' => AutoMamaParser::class,
        ]));
        $queueAutoRuId = Yii::$app->queueAutoRu->push(new PriceParserJob([
            'request_attribute' => 'avg_auto_ru',
            'request' => $request,
            'parser' => AutoRuParser::class,
        ]));

        Yii::$app->queueAutoParsing->delay(60)->push(new AutoParsingCheckJob([
            'request' => $request,
            'queueDromId' => $queueDromId,
            'queueAutoMamaId' => $queueAutoMamaId,
            'queueAutoRuId' => $queueAutoRuId
        ]));

    }



    public function genVerifyCode()
    {
        $this->verify_code               = rand(100000, 999999);
        $this->getRequest()->verify_code = $this->verify_code;
        if ($this->getRequest()->type != Request::TYPE_DRAFT) {
            throw new ApiException('Заявка недоступна');
        }
        if ($this->getRequest()->user_id != $this->getUserId()) {
            throw new ApiException('Заявка недоступна этому пользователю');
        }
        if (!$this->getRequest()->save()) {
            //            throw new ApiException($this->getRequest()->getFirstErrors());
            throw new ApiException('Системная ошибка');
        }

        return $this->getRequest();
    }

    public function getRequest()
    {
        if ($this->_request === null) {
            $this->_request = new Request();
        }

        return $this->_request;
    }

    public function setRequest(Request $request)
    {
        $this->_request = $request;
    }

    public function fileValidate(Request $request)
    {
        $fileError       = [];
        $fileNotRequired = [
            'file_pensioner_foto',
            'file_passport_second_other',
        ];
        $filesByLabel    = ArrayHelper::index($request->getFiles(), 'label');
        foreach (Request::labelImage() as $label => $fileLabelText) {
            if ($this->unemployed_type == 1 && $label == 'file_pensioner_foto') {
            } elseif (in_array($label, $fileNotRequired)) {
                continue;
            }
            if (!isset($filesByLabel[ $label ])) {
                $fileError[] = $fileLabelText . ' не загружен';
            }
        }
        if ($fileError) {
            throw new ApiException($fileError);
        }
    }

    /**
     * @return User
     */
    public function getUserId()
    {
        return $this->_userId ? $this->_userId : user()->id;
    }

    public function setUserId($id)
    {
        $this->_userId = $id;
    }

    public function getProfile()
    {
        if ($this->_profile === null) {
            $this->_profile = new UserProfile();
        }
        if (UserProfile::find()->where(['user_id' => $this->getUserId()])->count()) {
            $this->_profile = UserProfile::find()->where(['user_id' => $this->getUserId()])->one();
        }

        return $this->_profile;
    }

    public function getUser()
    {
        if ($this->_user === null) {
            $this->_user = new User();
        }
        if (User::findOne($this->getUserId())) {
            $this->_user = User::findOne($this->getUserId());
        }

        return $this->_user;
    }

    /**
     * Get request timezone
     *
     * @param Request $request
     *
     * @return Request
    */
    protected function getRequestTimezone(Request $request)
    {
        if ($this->reg_region && $request->reg_region !== $this->reg_region) {
            $request->timezone_reg = DadataHelper::getAddressTimezone($this->reg_region.' '.$this->reg_city);
        }

        if ($this->life_region && $request->life_region !== $this->life_region) {
            if ($this->reg_region.' '.$this->reg_city === $this->life_region.' '.$this->life_city) {
                $request->timezone_life = $request->timezone_reg;
            } else {
                $request->timezone_life = DadataHelper::getAddressTimezone($this->life_region.' '.$this->life_city);
            }
        }

        if ($this->phone && $request->phone !== $this->phone) {
            $request->timezone_phone = DadataHelper::getPhoneTimezone($this->phone);
        }

        return $request;
    }
}
