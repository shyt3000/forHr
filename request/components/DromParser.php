<?php

namespace backend\modules\request\components;

use backend\modules\request\models\Request;
use PHPHtmlParser\Dom;

class DromParser extends PriceParser
{
    private $prices = [];

    private $mark;
    private $model;
    private $year;
    private $max_millage;
    private $min_millage;
    private $transmission;

    public $price_class = '.b-advItem__section_type_price .b-advItem__price';

    /** @var Request */
    public $request;

    public function __construct(Request $request)
    {
        $this->mark = $this->parseMark($request->car_sts_brand);
        $this->model = $this->parseModel($request->car_sts_model);
        $this->year = $request->car_sts_create_year;

        $this->max_millage = $this->getMaxMillage($request->car_sts_mileage);
        $this->min_millage = $this->getMinMillage($request->car_sts_mileage);
        $this->transmission = $this->parseTransmission($request->car_sts_kp);

        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    public function getAverage()
    {
        for ($i = 1; $i <= 100; $i++) {
            if ($this->countArticles($i)) {
                $this->getPrices($this->getHtml($this->getUrl($i)));
            } else {
                $i = 100;
            }
        }
        if (count($this->prices)) {
            $this->prices = array_filter($this->prices);
            return array_sum($this->prices) / count($this->prices);
        } else {
            return -1;
        }
    }

    /**
     * @param $dom
     */
    private function getPrices($dom)
    {
        foreach ($dom->find($this->price_class) as $key => $price) {
            array_push($this->prices, (int)$this->formatPrice($price->text));
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
     * @param $page
     * @return int
     * @throws \Exception
     */
    private function countArticles($page)
    {
        $dom = $this->getHtml($this->getUrl($page));
        return count($dom->find($this->price_class));
    }

    /**
     * @param int $page
     * @return string
     */
    private function getUrl($page = 1)
    {
        $params = [
            'minyear' => $this->year,
            'maxyear' => $this->year,
            'transmission' => $this->transmission,
            'unsold' => 1,
            'minprobeg' => $this->min_millage,
            'maxprobeg' => $this->max_millage
        ];
        if ($page === 1) {
            $url = "https://auto.drom.ru/" . $this->mark . "/" . $this->model . "/";
        } else {
            $url = "https://auto.drom.ru/" . $this->mark . "/" . $this->model . "/page" . $page . "/";
        }
        return $url . "?" . http_build_query($params);
    }

    /**
     * @param $url
     * @return Dom
     * @throws \Exception
     */
    private function getHtml($url)
    {
        $dom = new Dom;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $content = curl_exec($ch);
        if (curl_errno($ch)) {
            echo curl_error($ch);
        } else {
            $content = substr($content, 0, strpos($content, '<div class="b-footer b-footer_no-gutter">'));
            $content = strstr($content, '<div class="b-wrapper">');

            $content = $this->beautify($content);

            $dom->loadStr($content, [
                'cleanupInput' => false,
                'removeDoubleSpace' => false
            ]);

        }
        curl_close($ch);

        return $dom;
    }

    /**
     * @param $html
     * @param array $config
     * @param string $encoding
     * @return string|void
     * @throws \Exception
     */
    private function beautify($html, array $config = [], $encoding = 'utf8')
    {
        if (!extension_loaded('tidy')) {
            throw new \Exception("Tidy extension is missing!");
            return;
        }
        $config += [
            'clean' => TRUE,
            'doctype' => 'omit',
            'indent' => 2,
            'output-html' => TRUE,
            'tidy-mark' => FALSE,
            'wrap' => 0,
            'new-blocklevel-tags' => 'article aside audio bdi canvas details dialog figcaption figure footer header hgroup main menu menuitem nav section source summary template track video',
            'new-empty-tags' => 'command embed keygen source track wbr',
            'new-inline-tags' => 'audio command datalist embed keygen mark menuitem meter output progress source time video wbr',
        ];
        $html = tidy_parse_string($html, $config, $encoding);
        tidy_clean_repair($html);
        return '<!DOCTYPE html>' . PHP_EOL . $html;
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