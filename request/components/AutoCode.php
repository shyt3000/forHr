<?php

namespace backend\modules\request\components;

use AvtoDev\B2BApi\Exceptions\B2BApiException;
use AvtoDev\B2BApi\Tokens\AuthToken;
use AvtoDev\B2BApi\Clients\v1\Client;
use AvtoDev\B2BApi\Responses\DataTypes\Report\ReportData;

use backend\components\ApiException;
use backend\modules\request\models\AutoCodeStorage;
use backend\modules\request\models\Request;
use yii\base\Component;
use yii\helpers\Json;

class AutoCode extends Component
{

    public $base_uri = 'https://b2bapi.avtocod.ru/b2b/api/v1/';

    public $username;
    public $password;
    public $domain;

    public $test = false;
    public $expiredTime = 300;


    /** @var Client */
    private $client;
    private $token;

    public function init()
    {
        $configuration = [
            'api' => [
                'versions' => [
                    'v1' => [
                        'base_uri' => $this->base_uri,
                    ],
                ],
            ],
            'use_api_version' => 'v1',
            'is_test' => false,
        ];

        $this->client = new Client($configuration);
        $this->token = AuthToken::generate($this->username, $this->password, $this->domain);

        parent::init();
    }

    /**
     * Метод для мониторинга
     *
     * @throws \AvtoDev\B2BApi\Exceptions\B2BApiException
     */
    public function ping()
    {
        $response = $this->client->dev()->ping();
        $response->getValue('value');
    }

    /**
     * @param $type
     * @param $query
     * @return array|mixed|null
     * @throws \AvtoDev\B2BApi\Exceptions\B2BApiException
     * @throws \AvtoDev\B2BApi\Exceptions\B2BApiInvalidArgumentException
     */
    public function createReport($type, $query)
    {
        $report_status = $this->client->user()
            ->report()
            ->make($this->token, $type, $query, 'Elvas_autocomplete_package_report@Elvas')
            ->data()
            ->first();

        $reportUid = $report_status->getContentValue('uid');
        return $reportUid;
    }

    /**
     * @param $reportUid
     * @return \AvtoDev\B2BApi\Responses\DataTypes\DataTypeInterface|null
     * @throws \AvtoDev\B2BApi\Exceptions\B2BApiException
     */
    public function findReport($reportUid)
    {
        return $this->client->user()->report()->get($this->token, $reportUid)
            ->data()
            ->first();
    }

    /**
     * Получаем данные
     *
     * @param Request $request
     * @param $type
     * @param $query
     * @return array|mixed|string|null
     * @throws ApiException
     */
    public function getData(Request $request, $type, $query)
    {
        try {
            // Проверяем не запрашивали ли ранее эти данные
            $storage = $this->findByStorage($request, $type, $query);

            if ($storage && $storage->data) {
                return Json::decode($storage->data);
            }

            if ($storage && !$storage->data) {

                $report = $this->findReport($storage->report_uid);

                if ($report instanceof ReportData) {
                    $is_completed = $report->generationIsCompleted();
                    if (! $is_completed) {
                        return 'NOT_COMPLETE';
                    }

                    $content = $report->getContent();
                    $storage->saveData($content);
                    return $content;
                }
            }
            if (!$storage) {
                $reportUid = $this->createReport($type, $query);
                $this->createStorage($request, $type, $query, $reportUid);
                return 'NOT_COMPLETE';
            }
            return null;
        } catch (B2BApiException $e) {
            \Yii::error($e);
            throw new ApiException('Не пройдена валидация');
        }

    }

    /**
     * Сохраняем данные по запросу
     * @param Request $request
     * @param $type
     * @param $query
     * @param $reportUid
     * @return bool
     */
    public function createStorage(Request $request, $type, $query, $reportUid)
    {
        $storage = new AutoCodeStorage();
        $storage->request_id = $request->id;
        $storage->user_id = $request->user_id;
        $storage->type = $type;
        $storage->query = $query;
        $storage->report_uid = $reportUid;

        return $storage->save();
    }

    /**
     * Поиск по предыдущим запросам
     *
     * @param Request $request
     * @param $type
     * @param $query
     * @return AutoCodeStorage
     */
    public function findByStorage(Request $request, $type, $query)
    {
        /** @var AutoCodeStorage $storage */
        $storage = AutoCodeStorage::find()
            ->where([
                'request_id' => $request->id,
                'type' => $type,
                'query' => $query,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $storage;
    }


    /**
     * Запрос просрочен
     *
     * @param AutoCodeStorage $storage
     * @return bool
     */
    public function isExpired(AutoCodeStorage $storage)
    {
        if (abs($storage->created_at - time()) >= $this->expiredTime) {
            return true;
        }
    }
}