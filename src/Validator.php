<?php

namespace App;

interface ValidatorInterface
{
    // Return array of errors, or empty array if no errors
    public function validate(array $data);
}

class Validator implements ValidatorInterface
{
    public function validate(array $data)
    {
        $errors = [];
        if (empty($data['name'])) {
            $errors['name'] = "Can't be blank";
        }
        if (mb_strlen($data['name']) < 4) {
            $errors['name'] = "Name must be greater than 4 characters";
        }
        if (empty($data['email'])) {
            $errors['email'] = "Can't be blank";
        }
        return $errors;
    }
}