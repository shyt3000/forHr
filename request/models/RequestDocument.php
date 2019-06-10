<?php


namespace backend\modules\request\models;

/**
 * This is the model class for table "request_document".
 *
 * @property int $id
 * @property string $name
 * @property string $label
 * @property string $template
 * @property string $date
 * @property string $header
 * @property string $footer
 */
class RequestDocument extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'request_document';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'label', 'template'], 'required'],
            [['template'], 'string'],
            [['date'], 'safe'],
            [['name', 'label'], 'string', 'max' => 200],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'label' => 'Label',
            'template' => 'Template',
            'date' => 'Date',
        ];
    }

    public function fields()
    {
        $fields = parent::fields();
        unset($fields['template']);
        unset($fields['header']);
        unset($fields['footer']);
        return $fields;
    }
}
