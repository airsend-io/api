<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Policy\Policies;

use CodeLathe\Core\Exception\UnsupportedPolicyTypeException;
use CodeLathe\Core\Utility\Json;

abstract class AbstractPolicy
{
    /**
     * @var string
     */
    private $rawValue;


    /**
     * AbstractPolicy constructor.
     * @param $value
     * @param bool $fromDB When true, we don't convert the value to raw
     */
    public function __construct($value, $fromDB = false)
    {
        if ($fromDB) {
            $this->rawValue = (string)$value;
        } else {
            $this->rawValue = $this->convertToRaw($value);
        }
    }

    protected function convertToRaw($value): string
    {
        switch (gettype($value)) {
            case 'array':
            case 'object':
                return Json::encode($value);
            default:
                return (string)$value;
        }
    }

    /**
     * Database key for the policy
     * @return string
     */
    abstract public static function getKey(): string;

    /**
     * Textual name for the policy
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Return the type that the value must be converted to when returning and from when setting
     *
     * supports int, float, bool, string, array (no keys), object (array with keys and subarrays)
     * @return string
     */
    abstract public function getType(): string;

    /**
     * @return mixed
     */
    abstract public static function getDefault();

    /**
     * @return mixed
     */
    public function getRawValue(): string
    {
        return $this->rawValue;
    }

    /**
     * Gets the converted/ready to use value for the policy
     * @throws UnsupportedPolicyTypeException
     * @return mixed
     */
    public function getValue()
    {
        if (!in_array($this->getType(), ['int', 'float', 'bool', 'string', 'object', 'array'])) {
            throw new UnsupportedPolicyTypeException($this->getType());
        }
        switch($this->getType()) {
            case 'object':
            case 'array':
                return Json::decode($this->rawValue, true);
            default:
                $value = $this->rawValue;
                settype($value, $this->getType());
                return $value;
        }
    }
}