<?php

namespace croxton\imgixer\models;

use craft\base\Model;

class Settings extends Model
{
    public $sources= array();
    public $transformSource = null;

    public function rules()
    {
        return [
            [['sources'], 'required'],
            [['transformSource']],
        ];
    }
}