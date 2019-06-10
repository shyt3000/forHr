<?php

namespace backend\modules\request\models;

/**
 * This is the model class for table "request_schedule".
 *
 * @property int $id
 * @property int $request_id
 * @property string $response
 * @property string $created_at
 * @property string $updated_at
 */
class RequestSchedule extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'request_schedule';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['request_id', 'response'], 'required'],
            [['request_id'], 'integer'],
            [['request_id'], 'unique'],
        ];
    }
}
