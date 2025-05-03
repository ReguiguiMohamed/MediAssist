<?php
class Validator {
    private $errors = [];
    private $data = [];
    private static $defaultMessages = [
        'required' => 'The {field} field is required',
        'email' => 'The {field} must be a valid email address',
        'min' => 'The {field} must be at least {param} characters',
        'max' => 'The {field} must not exceed {param} characters',
        'matches' => 'The {field} does not match {param}',
        'alpha' => 'The {field} may only contain letters',
        'alpha_numeric' => 'The {field} may only contain letters and numbers',
        'numeric' => 'The {field} must be a number',
        'url' => 'The {field} must be a valid URL',
        'date' => 'The {field} must be a valid date'
    ];

    public function __construct($data = []) {
        $this->data = $data;
    }

    public function validate($rules) {
        foreach ($rules as $field => $ruleString) {
            $this->validateField($field, $ruleString);
        }
        return empty($this->errors);
    }

    private function validateField($field, $ruleString) {
        if (!isset($this->data[$field])) {
            if (strpos($ruleString, 'required') !== false) {
                $this->addError($field, self::$defaultMessages['required']);
            }
            return;
        }

        $value = trim($this->data[$field]);
        $rules = explode('|', $ruleString);

        foreach ($rules as $rule) {
            $params = [];
            if (strpos($rule, ':') !== false) {
                [$rule, $param] = explode(':', $rule);
                $params = explode(',', $param);
            }

            switch ($rule) {
                case 'required':
                    if (empty($value)) {
                        $this->addError($field, self::$defaultMessages['required']);
                    }
                    break;

                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $this->addError($field, self::$defaultMessages['email']);
                    }
                    break;

                case 'min':
                    if (strlen($value) < $params[0]) {
                        $this->addError($field, str_replace(
                            ['{field}', '{param}'],
                            [$field, $params[0]],
                            self::$defaultMessages['min']
                        ));
                    }
                    break;

                case 'max':
                    if (strlen($value) > $params[0]) {
                        $this->addError($field, str_replace(
                            ['{field}', '{param}'],
                            [$field, $params[0]],
                            self::$defaultMessages['max']
                        ));
                    }
                    break;

                case 'matches':
                    if ($value !== $this->data[$params[0]]) {
                        $this->addError($field, str_replace(
                            ['{field}', '{param}'],
                            [$field, $params[0]],
                            self::$defaultMessages['matches']
                        ));
                    }
                    break;

                case 'alpha':
                    if (!ctype_alpha($value)) {
                        $this->addError($field, self::$defaultMessages['alpha']);
                    }
                    break;

                case 'alpha_numeric':
                    if (!ctype_alnum($value)) {
                        $this->addError($field, self::$defaultMessages['alpha_numeric']);
                    }
                    break;
            }
        }
    }

    private function addError($field, $message) {
        $this->errors[$field] = str_replace('{field}', $field, $message);
    }

    public function getErrors() {
        return $this->errors;
    }

    public function hasError($field) {
        return isset($this->errors[$field]);
    }

    public function getError($field) {
        return $this->errors[$field] ?? '';
    }

    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}