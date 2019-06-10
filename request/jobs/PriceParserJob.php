<?php

namespace backend\modules\request\jobs;

use backend\modules\request\components\PriceParser;
use backend\modules\request\models\Request;
use backend\modules\request\models\RequestUmfo;

class PriceParserJob extends \yii\base\BaseObject implements \yii\queue\JobInterface
{
    /** @var string */
    public $request_attribute;

    /** @var Request */
    public $request;

    /** @var PriceParser */
    public $parser;

    public function execute($queue)
    {
        echo 'Start Job: ', $this->parser, PHP_EOL;
        $request = $this->request;

        try {
            /** @var PriceParser $parser */
            $parser = \Yii::createObject($this->parser, [
                $request,
            ]);

            $request->{$this->request_attribute} = $parser->getAverage();
            $request->save(false, [$this->request_attribute]);
            echo 'Result Job: ', $this->parser, ' | ', $request->{$this->request_attribute}, PHP_EOL;


        } catch (\Exception $e) {
            echo 'Error: ', $e->getMessage(), PHP_EOL;
        }

        echo 'Stop Job: ', $this->parser, PHP_EOL;
    }
}