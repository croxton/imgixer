<?php

namespace croxton\imgixer\models;

use craft\base\Model;

class Settings extends Model
{
    public $sources= array();
    public $transformSource = null;

    public function rules(): array
    {
        return [
            [['sources'], 'required'],
            [['transformSource']],
        ];
    }
}