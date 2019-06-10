<?php

namespace backend\modules\request\components;

use backend\modules\request\models\Request;

class AutoMamaParser extends PriceParser
{
    private $prices = 0;
    private $count = 0;

    private $mark;
    private $model;
    private $year;

    private $notFound = false;

    public function __construct(Request $request)
    {

        $this->mark = $this->parseMark($request->car_sts_brand);
        $this->model = $this->parseModel($request->car_sts_model);
        $this->year = $request->car_sts_create_year;

        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    public function getAverage()
    {
        $this->getPrices($this->getData($this->getUrl()));


        if ($this->prices > 0 && $this->count > 0) {
            return $this->prices / $this->count;
        } else {
            return -1;
        }
    }

    /**
     * @param $dom
     */
    private function getPrices($data)
    {
        if (!$this->notFound) {
            foreach ($data['searchResult']['items'] as $item) {
                if ($item['status'] != 'closed') {
                    $this->prices += $item['buyNowPrice'];
                    $this->count++;
                }
            }
        } else {
            $this->prices = 0;
            $this->count = 0;
        }
    }

    /**
     * @param $price
     * @return null|string|string[]
     */
    private function formatPrice($price)
    {
        return preg_replace('/[^0-9]/', '', $price);
    }

    /**
     * @param int $page
     * @return string
     */
    private function getUrl()
    {
        $params = [
            'p1' => $this->mark,
            'p2' => $this->model,
            'yearFrom' => $this->year,
            'yearTo' => $this->year,
        ];
        $url = "https://automama.ru/api/v2/auctions/search";
        return $url . "?" . http_build_query($params);
    }

    /**
     * @param $url
     * @return int|mixed
     */
    private function getData($url)
    {
        try {
            $result = file_get_contents($url);
            return json_decode($result, true);
        } catch (\Exception $e) {
            $this->notFound = true;
        }
        return 0;
    }

    /**
     * @param $mark
     * @return mixed
     */
    private function parseMark($mark)
    {
        return str_replace(' ', '_', strtolower($mark));
    }

    /**
     * @param $model
     * @return mixed
     */
    private function parseModel($model)
    {
        return str_replace(' ', '_', strtolower($model));
    }

    /**
     * @param $transmission
     * @return int
     */
    private function parseTransmission($transmission)
    {
        if ($transmission === 'A') {
            return 2;
        } else {
            return 1;
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