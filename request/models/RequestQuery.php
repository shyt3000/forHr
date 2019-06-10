<?php


namespace backend\modules\request\models;

/**
 * This is the ActiveQuery class for [[Request]].
 *
 * @see Request
 */
class RequestQuery extends \yii\db\ActiveQuery
{

    public $requestActiveCountDays = 6;

    public function getAlias()
    {
        return Request::tableName();
    }

    /**
     * {@inheritdoc}
     * @return Request[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return Request|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }

    public function active()
    {
        return $this->andWhere([$this->getAlias() . '.type' => Request::TYPE_ACTIVE]);
    }

    public function lastActive()
    {
        $query = $this->andWhere(
            ['or',
                ['and',
                    ['=', $this->getAlias() . '.type', Request::TYPE_ACTIVE],
                    'DATE_ADD(' . $this->getAlias() . '.date, INTERVAL :count DAY) > NOW()'
                ],
                ['not in', $this->getAlias() . '.type', [Request::TYPE_ACTIVE, Request::TYPE_REJECT, Request::TYPE_DRAFT]],
            ]
            ,
            [':count' => $this->requestActiveCountDays]
        )
            ->orderBy(['id' => SORT_DESC])
            ->limit(1);
        return $query;
    }

    public function finished()
    {
        return $this->andWhere([$this->getAlias() . '.type' => Request::TYPE_AGREED]);
    }

    public function draft()
    {
        return $this->andWhere([$this->getAlias() . '.type' => Request::TYPE_DRAFT]);
    }

    public function lastDraftByUser($userid)
    {
        return $this->draft()
            ->andWhere([$this->getAlias() . '.user_id' => $userid])
            ->orderBy([$this->getAlias() . '.date' => SORT_DESC]);
    }

    public function byUser($userId)
    {
        return $this->andWhere([$this->getAlias() . '.user_id' => $userId]);
    }

    public function lastDays($countDays)
    {
        return $this->andWhere('DATE_ADD(' . $this->getAlias() . '.date, INTERVAL :count DAY) > NOW()', [':count' => $countDays]);
    }
}
