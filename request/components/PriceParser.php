<?php

namespace backend\modules\request\components;

abstract class PriceParser extends \yii\base\Component
{
    abstract public function getAverage();
}