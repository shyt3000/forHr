<?php


namespace backend\modules\request\models;

use Yii;

/**
 * This is the model class for table "request_agreement".
 *
 * @property int $id
 * @property string $date
 * @property string $name
 * @property string $label
 * @property int $document_id
 * @property int $priority
 */
class RequestAgreement extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'request_agreement';
    }

    public function rules()
    {
        return [
            [['date'], 'safe'],
            [['name', 'label'], 'required'],
            [['label'], 'string'],
            [['document_id'], 'integer'],
            [['priority'], 'integer'],
            [['name'], 'string', 'max' => 100],
            [['name'], 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'date' => 'Date',
            'name' => 'Name',
            'label' => 'Label',
            'document_id' => 'Document ID',
            'priority' => 'Priority',
        ];
    }
}
