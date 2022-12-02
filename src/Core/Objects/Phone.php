<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;

class Phone
{
    protected $phone;

    public static function createDirect(bool $isValid, string $number, string $localFormat, string $internationalFormat,
                                    string $countryCode, string $countryName, string $location,
                                    string $carrier, string $lineType) : self
    {
        $instance = new self();
        $instance->phone['is_valid']        = $isValid;
        $instance->phone['number']          = $number;
        $instance->phone['local_format']    = $localFormat;
        $instance->phone['intl_format']     = $internationalFormat;
        $instance->phone['country_code']    = $countryCode;
        $instance->phone['country_name']    = $countryName;
        $instance->phone['location']        = $location;
        $instance->phone['carrier']         = $carrier;
        $instance->phone['line_type']       = $lineType;
        $instance->phone['updated_on']      = date('Y-m-d H:i:s');
        return $instance;
    }

    public static function create(array $data) : self
    {
        $instance = new self();
        $instance->phone['is_valid']        = isset($data['valid']) ? Convert::toBool($data['valid']) : false;
        $instance->phone['number']          = isset($data['number']) ? Convert::toStrNull($data['number']) : '';
        $instance->phone['local_format']    = isset($data['local_format']) ? Convert::toStrNull($data['local_format']) : $instance->phone['number'];
        $instance->phone['intl_format']     = isset($data['international_format']) ? Convert::toStrNull($data['international_format']) : $instance->phone['number'];
        $instance->phone['country_prefix']  = isset($data['country_prefix']) ? Convert::toStrNull($data['country_prefix']) : '';
        $instance->phone['country_code']    = isset($data['country_code']) ? Convert::toStrNull($data['country_code']) : '';
        $instance->phone['country_name']    = isset($data['country_name']) ? Convert::toStrNull($data['country_name']) : '';
        $instance->phone['location']        = isset($data['location']) ? Convert::toStrNull($data['location']) : '';
        $instance->phone['carrier']         = isset($data['carrier']) ? Convert::toStrNull($data['carrier']) : '';
        $instance->phone['line_type']       = isset($data['line_type']) ? Convert::toStrNull($data['line_type']) : '';
        $instance->phone['updated_on']      = date('Y-m-d H:i:s');
        return $instance;
    }

    public static function withDBData(array $a_record) : ?self
    {
        if(array_filter($a_record)){
            $instance = new self();
            $instance->loadWithDBData($a_record);
            return $instance;
        }
        else
            return null;
    }

    public function loadWithDBData(array $a_record) : void
    {
        $this->phone['id']               = Convert::toIntNull($a_record['id']);
        $this->phone['is_valid']        = Convert::toBool($a_record['is_valid']);
        $this->phone['number']          = Convert::toStrNull($a_record['number']);
        $this->phone['local_format']    = Convert::toStrNull($a_record['local_format']);
        $this->phone['intl_format']     = Convert::toStrNull($a_record['intl_format']);
        $this->phone['country_prefix']  = Convert::toStrNull($a_record['country_prefix']);
        $this->phone['country_code']    = Convert::toStrNull($a_record['country_code']);
        $this->phone['country_name']    = Convert::toStrNull($a_record['country_name']);
        $this->phone['location']        = Convert::toStrNull($a_record['location']);
        $this->phone['carrier']         = Convert::toStrNull($a_record['carrier']);
        $this->phone['line_type']       = Convert::toStrNull($a_record['line_type']);
        $this->phone['updated_on']      = Convert::toStrNull($a_record['updated_on']);
    }

    public function getId() : int
    {
        return $this->phone['id'];
    }

    public function setId(int $value) : void
    {
        $this->phone['id'] = $value;
    }

    public function isValid() : bool
    {
        return $this->phone['is_valid'];
    }

    public function getNumber() : string
    {
        return $this->phone['number'];
    }

    public function getLocalFormat() : string
    {
        return $this->phone['local_format'];
    }

    public function getInternationalFormat() : string
    {
        return $this->phone['intl_format'];
    }

    public function getCountryCode() : string
    {
        return $this->phone['country_code'];
    }

    public function getCountryName() : string
    {
        return $this->phone['country_name'];
    }

    public function getLocation() : string
    {
        return $this->phone['location'];
    }

    public function getCarrier() : string
    {
        return $this->phone['carrier'];
    }

    public function getLineType() : string
    {
        return $this->phone['line_type'];
    }

    public function getUpdateOn() : string
    {
        return $this->phone['updated_on'];
    }

    public function setUpdateOn(string $value) : void
    {
        $this->phone['updated_on'] = $value;
    }

    public function getArray() : array
    {
        return $this->phone;
    }
}