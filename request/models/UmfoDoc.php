<?php

namespace backend\modules\request\models;

use Yii;

/**
 * This is the model class for table "umfo_doc".
 *
 * @property int $id
 * @property int $request_id
 * @property string $label
 * @property string $path
 * @property int $type
 * @property string $date
 */
class UmfoDoc extends \yii\db\ActiveRecord
{
    const LABEL_AUTO = 'Отчёт о состоянии автомобиля';
    const LABEL_INSURANSE = 'ПОЛИС страхования от несчастных случаев';
    
    const TYPE_AUTO = 1;
    const TYPE_INSURANSE = 2;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'umfo_doc';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['request_id', 'type'], 'integer'],
            [['date'], 'safe'],
            [['label', 'path'], 'string', 'max' => 255],
            [['request_id'], 'required'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'request_id' => 'Request ID',
            'label' => 'Label',
            'path' => 'Path',
            'type' => 'Type',
            'date' => 'Date',
        ];
    }
}
