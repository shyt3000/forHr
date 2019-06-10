<?php

namespace backend\modules\request\models;

use backend\modules\file\behaviours\FileBehaviour;
use backend\modules\payment\behaviours\EcommpayTokenBehaviour;
use backend\modules\user\models\User;
use common\helpers\LabelHelper;
use common\helpers\DateHelper;
use backend\modules\contract\models\Contract;
use Yii;

/**
 * This is the model class for table "{{%request}}".
 *
 * @property int         $id
 * @property string      $date
 * @property int         $user_id
 * @property int         $sum
 * @property int         $sum_time
 * @property string      $type
 * @property int         $phone
 * @property string      $email
 * @property string      $sex
 * @property string      $email_distribution
 * @property string      $lastname
 * @property string      $firstname
 * @property string      $middlename
 * @property string      $birthday
 * @property string      $passport_serial
 * @property string      $passport_number
 * @property string      $passport_place
 * @property string      $passport_date
 * @property string      $passport_place_birth
 * @property string      $passport_place_kod
 * @property string      $passport_adress
 * @property string      $reg_region
 * @property string      $reg_city
 * @property string      $reg_street
 * @property string      $reg_house
 * @property string      $reg_korpus
 * @property string      $reg_kv
 * @property string      $reg_index
 * @property string      $reg_building
 * @property string      $life_building
 * @property string      $life_index
 * @property string      $life_region
 * @property string      $life_city
 * @property string      $life_street
 * @property string      $life_house
 * @property string      $life_korpus
 * @property string      $life_kv
 * @property string      $skype
 * @property string      $phone_first
 * @property string      $phone_comm_first
 * @property string      $phone_second
 * @property string      $phone_comm_second
 * @property string      $phone_third
 * @property string      $phone_comm_third
 * @property string      $income_month
 * @property string      $unemployed_type
 * @property string      $pensioner_number
 * @property string      $additional_income
 * @property string      $debts
 * @property int         $debts_sum
 * @property int         $debts_periodicity
 * @property string      $job_place
 * @property string      $job_address
 * @property string      $job_phone
 * @property string      $job_position
 * @property int         $job_experience
 * @property string      $car_pts_number
 * @property string      $car_pts_vin
 * @property string      $car_pts_model
 * @property string      $car_pts_brand
 * @property string      $car_pts_category
 * @property int         $car_pts_create_year
 * @property string      $car_pts_color
 * @property int         $car_pts_power
 * @property string      $car_pts_special
 * @property string      $car_pts_date
 * @property string      $car_pts_place
 * @property string      $car_sts_number
 * @property string      $car_sts_vin
 * @property string      $car_sts_model
 * @property string      $car_sts_brand
 * @property string      $car_sts_type
 * @property int         $car_sts_create_year
 * @property string      $car_sts_color
 * @property int         $car_sts_mileage
 * @property string      $car_sts_crash
 * @property string      $car_sts_kp
 * @property string      $car_sts_date
 * @property string      $car_sts_place
 * @property string      $payment_method
 * @property string      $payment_number
 * @property string      $verify_code
 * @property string      $insurance
 * @property string      $auto_report
 * @property int         $step
 * @property string      $utm
 * @property string      $date_update
 * @property int         $action
 * @property int         $avg_auto_mama
 * @property int         $avg_auto_ru
 * @property int         $avg_drom_ru
 * @property int         $job_inn
 * @property string      $employment_type
 * @property string      $snils
 * @property string      $car_gos_number
 * @property boolean     $parsed
 * @property int         $adv_market_price
 * @property int         $collateral_price
 * @property string      $timezone_phone
 * @property string      $timezone_reg
 * @property string      $timezone_life
 * @property string      $ecommpay_synonym
 *
 * @property RequestUmfo $requestUmfo
 *
 * @method \backend\modules\file\models\File[] getFiles()
 */
class Request extends \yii\db\ActiveRecord
{

    const TYPE_ACTIVE = 1; // Отправлена на проверку
    const TYPE_DRAFT = 2; // Черновик
    const TYPE_AGREED = 3; // Одобрена ожидает подтверждения клиента через СМС
    const TYPE_REJECT = 4; // Отклонена
    const TYPE_PAYMENT_PROCESSING = 5; // Производится выплата займа, создание договора
    const TYPE_PAYMENT_SUCCESS = 6; // Выплата и создание договора выполнено успешно
    const TYPE_PAYMENT_ERROR = 7; // Выплата и создание договора = "Что то пошло не так"
    const TYPE_REPEATED = 8; // пере прескоринг
    const TYPE_DELAYED = 9; // Отложенная

    const PAYMENT_METHOD_QIWI = 'qiwi';
    const PAYMENT_METHOD_YANDEX = 'yandex'; 
    const PAYMENT_METHOD_CARD = 'card';
    const PAYMENT_METHOD_CASH = 'cash';
    const PAYMENT_METHOD_YANDEX_CARD = 'yandexCard';

    const ACTION_CALL_HELP = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%request}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'type'], 'required'],
            [['user_id'], 'integer'],
            [['action'], 'default', 'value' => '0'],
            [['insurance', 'email_distribution', 'auto_report'], 'default', 'value' => 'N'],
            ['payment_method', 'in', 'range' => [
                self::PAYMENT_METHOD_QIWI, 
                self::PAYMENT_METHOD_YANDEX, 
                self::PAYMENT_METHOD_CARD, 
                self::PAYMENT_METHOD_CASH,
                self::PAYMENT_METHOD_YANDEX_CARD
            ]]
        ];
    }

    public function behaviors()
    {
        return [
            [
                'class' => FileBehaviour::class,
                'type'  => 'request',
            ],
            // Обновляет EcommpayToken
            [
                'class' => EcommpayTokenBehaviour::class,
                'model' => $this
            ],
        ];
    }

    public static function find()
    {
        return new RequestQuery(get_called_class());
    }

    public static function labelImage($label = null)
    {
        $labels = [
            'file_foto_face'             => 'Идентификационное фото',
            'file_passport_first'        => 'Снимок первого разворота паспорта',
            'file_passport_second'       => 'Снимок разворота с пропиской',
            'file_pensioner_foto'        => 'Снимок пенсионного удостоверения',
            'file_pts_first'             => 'Снимок ПТС',
            'file_pts_second'            => 'Снимок ПТС обратная сторона',
            'file_sts_first'             => 'Снимок СТС',
            'file_sts_second'            => 'Снимок СТС обратная сторона',
            'file_car_front'             => 'Снимок автомобиля вид спереди',
            'file_car_back'              => 'Снимок автомобиля вид сзади',
            'file_car_right'             => 'Снимок автомобиля правый бок',
            'file_car_left'              => 'Снимок автомобиля левый бок',
            'file_car_vin'               => 'Снимок VIN-номера на двигателе/кузове автомобиля',
            'file_passport_second_other' => 'Снимок разворота с пропиской',
        ];

        return is_null($label) ? $labels : (isset($labels[ $label ]) ? $labels[ $label ] : null);
    }

    public function fields()
    {
        $fields                             = array_keys($this->attributes);
        $fields                             = array_combine($fields, $fields);
        $fields['fio']                      = function ($request) {
            return $request->fio;
        };
        $fields['email_distribution_label'] = function ($request) {
            return LabelHelper::yn($request->email_distribution);
        };
        $fields['insurance_label']          = function ($request) {
            return LabelHelper::yn($request->insurance);
        };
        $fields['sex_label']                = function ($request) {
            return LabelHelper::sex($request->sex);
        };
        $fields['avg_estimated']            = function ($request) {
            return $request->getAvgEstimated();
        };
        $fields['status']                   = function ($request) {
            return $request->getStatus();
        };
        $fields['debts_label']              = function ($request) {
            return LabelHelper::yn($request->debts);
        };
        $fields['additional_income_label']  = function ($request) {
            return LabelHelper::yn($request->additional_income);
        };
        $fields['job_experience_label']     = function ($request) {
            return ag(self::enumExperience(), $request->job_experience);
        };
        $fields['reg_date'] = function ($request) {
            return DateHelper::convertToTimezone($request->timezone_reg);
        };
        $fields['life_date'] = function ($request) {
            return DateHelper::convertToTimezone($request->timezone_life);
        };
        $fields['phone_date'] = function ($request) {
            return DateHelper::convertToTimezone($request->timezone_phone);
        };

        return $fields;
    }

    public static function enumStatus()
    {
        return [
            self::TYPE_DRAFT  => 'Оформление',
            self::TYPE_ACTIVE => 'Проверка',
            self::TYPE_AGREED => 'Согласована',
            self::TYPE_REJECT => 'Отказ',
        ];
    }

    public static function enumExperience()
    {
        return [
            "00.00-01.00" => "Менее года",
            "01.00-03.00" => "1-3 года",
            "03.00-05.00" => "3-5 лет",
            "05.00-99.99" => "Более 5 лет",
        ];
    }

    public function getStatus()
    {
        return ag(self::enumStatus(), $this->type);
    }

    public function getSexLabel()
    {
        return $this->sex == 'M' ? 'Муж.' : 'Жен.';
    }

    public function getFio()
    {
        return trim($this->lastname . ' '. $this->firstname . ' ' . $this->middlename);
    }

    public function getAvgEstimated()
    {
        $list = [];
        if ($this->avg_auto_mama > 0) array_push($list, $this->avg_auto_mama);
        if ($this->avg_drom_ru > 0) array_push($list, $this->avg_drom_ru);
        if ($this->avg_auto_ru > 0) array_push($list, $this->avg_auto_ru);
        if (count($list))
            return array_sum($list) / count($list);

        return 0;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRequestUmfo()
    {
        return $this->hasOne(RequestUmfo::class, ['request_id' => 'id']);
    }
    
    public function getContract()
    {
        return $this->hasOne(Contract::className(), ['request_id' => 'id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getActiveRequestByUser()
    {
        return $this->hasMany(Request::class, ['user_id' => 'user_id'])->onCondition(['!=', 'type', self::TYPE_DRAFT]);
    }
    
    public function saveAverage()
    {
        if($this->getAvgEstimated()>0) {
            $this->adv_market_price = round($this->getAvgEstimated(), 2);
        }
        if($this->adv_market_price > 0) {
            $this->collateral_price =  round(($this->adv_market_price * 0.75), 2);
        }

        $this->type = 1;
        $this->save();
    }
}
