<?php

namespace backend\modules\request\components;

use backend\modules\request\models\Request;
use GuzzleHttp\{Client, Cookie\FileCookieJar, Cookie\SetCookie};
use yii\base\Exception;
use yii\helpers\Json;
use Yii;

class AutoRuParser extends PriceParser
{
    private $mark;
    private $model;
    private $year;
    private $max_millage;
    private $min_millage;
    private $transmission;

    private $client;
    private $cookieJar;
    private $debug = false;

    public $cookieFile = '@runtime/auto.ru.cookie.jar.txt';
    public $proxy;


    public function __construct(Request $request)
    {
        $this->mark = $this->parseMark($request->car_sts_brand);
        $this->model = $this->parseModel($request->car_sts_model);
        $this->year = $request->car_sts_create_year;

        $this->max_millage = $this->getMaxMillage($request->car_sts_mileage);
        $this->min_millage = $this->getMinMillage($request->car_sts_mileage);
        $this->transmission = $this->parseTransmission($request->car_sts_kp);


        $this->client = new Client(['connect_timeout' => 30]);
        $cookieFile = Yii::getAlias($this->cookieFile);
        $this->cookieJar = new FileCookieJar($cookieFile, TRUE);

        // Добавляем в куки параметры соглашения с GDPR
        $this->acceptGdpr();

        //$this->proxy = '117.102.94.148:51712';
        parent::__construct();
    }

    public function acceptGdpr()
    {

        $this->cookieJar->setCookie(new SetCookie([
            'Domain'  => '.auto.ru',
            'Name'    => 'gdpr',
            'Value'   => '1',
            'Discard' => true,
            'Expires' => '2020-04-28T19:17:30.062Z',
        ]));
    }

    public function getAverage()
    {
        $params = [
            //'geo_id' => '39',
            'mark_model_nameplate' => "{$this->mark}#{$this->model}",
            'section' => 'all',
            'sort' => 'fresh_relevance_1-desc',
            'transmission' => $this->transmission,
            'year_from' => $this->year,
            'year_to' => $this->year,
            'km_age_from' => $this->min_millage,
            'km_age_to' => $this->max_millage,
        ];
        $response = $this->sendRequest($params);
        if (!$response) {
            throw new Exception('Не корректный ответ auto.ru');
        }
        $this->isBan($response);

        // Если IP другой странны требует соглашение
        if ($this->isAnotherCountry($response)) {
            throw new Exception('Нет соглашения для другой страны');
        }
        $responseData = Json::decode($response);
        $prices = [];
        foreach ($responseData['offers'] as $offer) {
            if (!empty($offer['price_info']['RUR'])) {
                $prices[] = $offer['price_info']['RUR'];
            }
        }
        $count = count($prices);
        if ($count < 1) {
            return -1;
        }
        $average = array_sum($prices) / $count;
        return $average;
    }

    /**
     * Установка cookies
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function setCookies()
    {
        $settings = [
            'debug' => $this->debug,
            'cookies' => $this->cookieJar
        ];

        if ($this->proxy) {
            $settings = array_merge($settings, [
                'proxy' => $this->proxy,
            ]);
        }
        $response = $this->client->request('GET', 'https://auto.ru/rostov-na-donu/cars/all/', $settings);

        $this->isBan((string)$response->getBody());

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Не удается получить cookie');
        }
    }

    /**
     * @param $params
     * @return string
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendRequest($params)
    {
        if (!$this->cookieJar->getCookieByName('_csrf_token')) {
            $this->setCookies();
            throw new Exception('Нет csrf токена');
        }

        $csrf_token = $this->cookieJar->getCookieByName('_csrf_token')->getValue();
        $headers = [
            'x-requested-with' => 'fetch',
            'Origin' => 'https://auto.ru',
            'x-csrf-token' => $csrf_token,
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) snap Chromium/73.0.3683.103 Chrome/73.0.3683.103 Safari/537.36',
            'DNT' => '1',
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
            'Accept' => '*/*',
            'X-Compress' => 'null',
            'Cache-Control' => 'no-cache',
        ];

        $settings = [
            'debug' => $this->debug,
            'headers' => $headers,
            'form_params' => $params,
            'cookies' => $this->cookieJar
        ];

        if ($this->proxy) {
            $settings = array_merge($settings, [
                'proxy' => $this->proxy,
            ]);
        }

        try {
            $response = $this->client->request('POST', 'https://auto.ru/-/ajax/listingCars/', $settings);
            return (string)$response->getBody();
        } catch (\Exception $e) {
            throw new Exception('HTTP ошибка auto.ru: ', $e->getMessage());
        }
    }

    /**
     * Проверка бана IP
     * @param $response
     * @throws Exception
     */
    private function isBan($response)
    {
        if (\strpos($response, 'Нам очень жаль, но&nbsp;запросы, поступившие с&nbsp;вашего IP-адреса, похожи на&nbsp;автоматические') > 0) {
            throw new Exception('IP в бане');
        }
    }

    /**
     * IP другой страны
     *
     * @param $response
     * @return bool
     */
    private function isAnotherCountry($response)
    {
        if (\strpos($response, 'By continuing using this website I accept that processing of my personal data will be held with compliance of Russian Federation laws') > 0) {
            return true;
        }
        return false;
    }

    /**
     * @param $mark
     * @return mixed
     */
    private function parseMark($mark)
    {
        return str_replace(' ', '_', mb_strtoupper($mark));
    }

    /**
     * @param $model
     * @return mixed
     */
    private function parseModel($model)
    {
        return str_replace(' ', '_', mb_strtoupper($model));
    }

    /**
     * @param $transmission
     * @return array|string
     */
    private function parseTransmission($transmission)
    {

        if ($transmission === 'A') {
            return [
                'AUTO',
                'AUTOMATIC',
                'ROBOT',
                'VARIATOR'
            ];
        } else {
            return 'MECHANICAL';
        }
    }

    /**
     * @param $millage
     * @return int
     */
    private function getMaxMillage($millage)
    {
        return $millage + 10000;
    }

    /**
     * @param $millage
     * @return int
     */
    private function getMinMillage($millage)
    {
        if ($millage - 10000 < 0) {
            return 0;
        }
        return $millage - 10000;
    }
}
