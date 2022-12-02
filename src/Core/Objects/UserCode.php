<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Utility\Convert;

class UserCode implements \JsonSerializable, ObjectInterface
{
    CONST USER_CODE_TYPE_ACCT_VERIFY    = 1;
    CONST USER_CODE_TYPE_PWD_RESET      = 2;
    CONST USER_CODE_TYPE_2FA            = 3;


    private $userCode;

    /**
     * Identity Code Create
     *
     * @param int $userId
     * @param string $codeType
     * @param string $code
     * @param string $expirationDate
     * @return UserCode
     */
    public static function create(int $userId, int $codeType, string $code, string $expirationDate) : UserCode
    {
        $instance = new self();
        $instance->userCode['user_id'] = $userId;
        $instance->userCode['code_type'] = $codeType;
        $instance->userCode['code'] = $code;
        $instance->userCode['expires'] =  date('Y-m-d H:i:s',strtotime($expirationDate));
        $instance->userCode['created_on'] = date("Y-m-d H:i:s");
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
        $this->userCode['user_id']      = Convert::toIntNull($a_record['user_id']);;
        $this->userCode['code_type']    = Convert::toIntNull($a_record['code_type']);
        $this->userCode['code']         = Convert::toStrNull($a_record['code']);
        $this->userCode['expires']      = Convert::toStrNull($a_record['expires']);
        $this->userCode['created_on']   = Convert::toStrNull($a_record['created_on']);
    }

    public function getId() : int
    {
        return $this->userCode['id'];
    }

    public function setId(int $id) : void
    {
        $this->userCode['id'] = $id;
    }

    public function getUserId() : int
    {
        return $this->userCode['user_id'];
    }

    public function setUserId(int $id) : void
    {
        $this->userCode['user_id'] = $id;
    }

    public function getCode() : string
    {
        return $this->userCode['code'];
    }

    public function setCode(string $code) : void
    {
        $this->userCode['code'] = $code;
    }

    public function getCodeType() : int
    {
        return $this->userCode['code_type'];
    }

    public function setCodeType(int $value) : void
    {
        $this->userCode['code_type'] = $value;
    }

    public function getExpires() : string
    {
        return $this->userCode['expires'];
    }

    public function setExpires(string $value)
    {
        $this->userCode['expires'] = date('Y-m-d H:i:s', strtotime($value));
    }


    public function getCreatedOn() : string
    {
        return $this->userCode['created_on'];
    }

    public function setCreatedOn(string $value) : void
    {
        $this->userCode['created_on'] = $value;
    }

    public function getArray() : array
    {
        return $this->userCode;
    }

    public function jsonSerialize() : array
    {
        return  $this->userCode;;
    }
}