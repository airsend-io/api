<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;

class UserSession implements \JsonSerializable, ObjectInterface
{
    /**
     * User object as array
     *
     * @var array
     */
    protected $session;

    /**
     * @param string $userId
     * @param string $issuer
     * @param string $token
     * @param string $ip
     * @param string $userAgent
     * @param string $expiryDate
     * @return UserSession
     */
    public static function create(string $userId, string $issuer, string $token, string $ip,
                                    string $userAgent, string $expiryDate) : UserSession
    {
        $instance = new self();
        $instance->session['user_id'] = $userId;
        $instance->session['issuer'] = $issuer;
        $instance->session['token'] = $token;
        $instance->session['ip'] = $ip;
        $instance->session['user_agent'] = $userAgent;
        $instance->session['expiry'] =  date('Y-m-d H:i:s',strtotime($expiryDate));;
        $instance->session['created_on'] = date('Y-m-d H:i:s');
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
        $this->session['user_id'] = Convert::toIntNull($a_record['user_id']);
        $this->session['issuer'] = Convert::toStrNull($a_record['issuer']);
        $this->session['token'] = Convert::toStrNull($a_record['token']);
        $this->session['ip'] = Convert::toStrNull($a_record['ip']);
        $this->session['user_agent'] = Convert::toStrNull($a_record['user_agent']);
        $this->session['expiry'] =  Convert::toStrNull($a_record['expiry']);
        $this->session['created_on'] = Convert::toStrNull($a_record['created_on']);
    }

    public function getId() : int
    {
        return $this->session['id'];
    }

    public function setId(int $id) : void
    {
        $this->session['id'] = $id;
    }

    public function geUserId() : int
    {
        return $this->session['user_id'];
    }

    public function setUserId(int $id) : void
    {
        $this->session['user_id'] = $id;
    }

    public function getIssuer() : string
    {
        return $this->session['issuer'];
    }

    public function setIssuer(string $value) : void
    {
        $this->session['issuer'] = $value;
    }

    public function getToken() : string
    {
        return $this->session['token'];
    }

    public function setToken(string $value) : void
    {
        $this->session['token'] = $value;
    }

    public function getIp() : string
    {
        return $this->session['ip'];
    }

    public function setIp(string $value) : void
    {
        $this->session['ip'] = $value;
    }

    public function getUserAgent() : string
    {
        return $this->session['user_agent'];
    }

    public function setUserAgent(string $value) : void
    {
        $this->session['user_agent'] = $value;
    }

    public function getExpiry() : string
    {
        return $this->session['expiry'];
    }

    public function setExpiry(string $expiryDate) : void
    {
        $this->session['expiry'] = date('Y-m-d H:i:s', strtotime($expiryDate));
    }

    public function getCreatedOn() : string
    {
        return $this->session['created_on'];
    }

    public function setCreatedOn(string $createdOn) : void
    {
        $this->session['created_on'] = $createdOn;
    }

    public function getArray() : array
    {
        return $this->session;
    }

    public function jsonSerialize() : array
    {
        return   $this->session;
    }
}
