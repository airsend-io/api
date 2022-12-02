<?php

class APICore
{
    public $curl_handle;
    public $server_url;
    public $start_time;
    public $end_time;
    public $user_agent = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36' ;
    public $jwtToken;
    public $jwtToken_set;

    public function __construct($SERVER_URL) {
        $this->init($SERVER_URL);
    }

    public function init($SERVER_URL) {
        $this->server_url = $SERVER_URL;
        $this->curl_handle = curl_init();
        curl_setopt($this->curl_handle, CURLOPT_COOKIEJAR, dirname(__FILE__) . DIRECTORY_SEPARATOR . "cookie.txt");
        curl_setopt($this->curl_handle, CURLOPT_COOKIEFILE, dirname(__FILE__) . DIRECTORY_SEPARATOR . "cookie.txt");
        curl_setopt($this->curl_handle, CURLOPT_TIMEOUT, 1200);
        curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($this->curl_handle, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($this->curl_handle, CURLOPT_MAXREDIRS, 4);
        curl_setopt($this->curl_handle, CURLOPT_HEADER, 0);
        curl_setopt($this->curl_handle, CURLOPT_USERAGENT, $this->user_agent);
    }

    protected function startTimer()
    {
        $this->start_time = microtime(true);
        $this->end_time = $this->start_time;
    }

    protected function stopTimer()
    {
        $this->end_time = microtime(true);
    }

    public function elapsed()
    {
        return round(abs($this->end_time - $this->start_time),3);
    }

    public function __destruct() {
        curl_close($this->curl_handle);
        if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . "cookie.txt")) {
            unlink(dirname(__FILE__) . DIRECTORY_SEPARATOR . "cookie.txt");
        }
    }

    protected function doGET($url) {
        curl_setopt($this->curl_handle, CURLOPT_URL, $url);
        curl_setopt($this->curl_handle, CURLOPT_POST, 0);
        curl_setopt($this->curl_handle, CURLOPT_HTTPGET, 1);
        curl_setopt($this->curl_handle, CURLOPT_HEADER, 0);
        if($this->jwtToken_set = '1')
        {
            $headers = array(
                "Authorization: Bearer " . $this->jwtToken
            );
            curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $headers);
        }
        return curl_exec($this->curl_handle);
    }

    protected function doPOSTWithToken($url, $postdata) {
        curl_setopt($this->curl_handle, CURLOPT_URL, $url);
        curl_setopt($this->curl_handle, CURLOPT_POST, 1);
        curl_setopt($this->curl_handle, CURLOPT_HTTPGET, 0);
        curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($this->curl_handle, CURLOPT_HEADER, 0);
        if($this->jwtToken_set = '1')
        {
            $headers = array(
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: Bearer " . $this->jwtToken
            );
            //var_dump($headers);
            curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $headers);
        }
        return curl_exec($this->curl_handle);
    }

    protected function doPOSTWithOutToken($url, $postdata) {
        curl_setopt($this->curl_handle, CURLOPT_URL, $url);
        curl_setopt($this->curl_handle, CURLOPT_POST, 1);
        curl_setopt($this->curl_handle, CURLOPT_HTTPGET, 0);
        curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curl_handle, CURLOPT_HEADER, 0);
        $headers = array(
             "Content-Type: application/x-www-form-urlencoded"
        );
        curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $headers);

        return curl_exec($this->curl_handle);
    }

    protected function doPOSTWithHeader($url, $postdata) {
        //clear token first
        $this->jwtToken = "";
        curl_setopt($this->curl_handle, CURLOPT_URL, $url);
        curl_setopt($this->curl_handle, CURLOPT_POST, 1);
        curl_setopt($this->curl_handle, CURLOPT_HTTPGET, 0);
        curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curl_handle, CURLOPT_HEADER, 0);
        $header = array(
            "Content-Type: application/x-www-form-urlencoded"
        );
        curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $header );
        $result =  curl_exec($this->curl_handle);
        //var_dump($result);
        //Check if http success code 200
        $httpcode = curl_getinfo($this->curl_handle, CURLINFO_HTTP_CODE);
        if ($httpcode!='200' ) {
            echo " Failed to login HTTP error";
            exit(0);
        }

        $obj = json_decode($result);
        $status = $obj->meta->ok;
        if($status == true)
        {
            $this->jwtToken = $obj->token;
            $this->jwtToken_set = '1';
        }

        return $result;
    }


 }

 class AirSendAPI extends  APICore{

     public function __construct($SERVER_URL) {
         parent::__construct($SERVER_URL);
     }

     public function __destruct() {
         parent::__destruct();
     }

     public function getLastRunTime()
     {
         return $this->elapsed();
     }

     public function userLogin($user, $password)
     {
         $this->startTimer();
         $url = $this->server_url.'/user.login';
         $postdata = 'user=' .$user . '&password='.$password;
         $response = $this->doPOSTWithHeader($url, $postdata);
         $this->stopTimer();
         return $response;
     }

     public function createUser($name, $email = "", $phone = "", $password)
     {
         $this->startTimer();
         $url = $this->server_url.'/user.create';
         if($email != "" && $email != null) {
             $email = '&email='. $email;
         }

         if($phone != "" && $phone != null) {
             $phone = '&phone='. $phone;
         }

         $postdata = 'name=' . $name .'&password=' . $password.$email.$phone;
         //echo $postdata;
         $response = $this->doPOSTWithOutToken($url, $postdata);
         $this->stopTimer();
         return $response;
     }

     public function userInfo($email = "", $phone = "", $user_id)
     {
         $this->startTimer();
         if($email != "" && $email != null) {
             $email = '&email='. $email;
         }

         if($phone != "" && $phone != null) {
             $phone = '&phone='. $phone;
         }
         $url = $this->server_url.'/user.info?user_id='.$user_id.$email.$phone;
         $response = $this->doGET($url);
         //var_dump($response);
         $this->stopTimer();
         return $response;

     }

     public function userProfileSet($email = "", $phone = "", $name)
     {
         $this->startTimer();
         if($email != "" && $email != null) {
             $email = '&email='. $email;
         }

         if($phone != "" && $phone != null) {
             $phone = '&phone='. $phone;
         }
         $url = $this->server_url.'/user.profile.set';
         $postdata = "name=".$name.$email.$phone;
         $response = $this->doPOSTWithToken($url, $postdata);
         //var_dump($response);
         $this->stopTimer();
         return $response;

     }

     public function channelCreate($channelName, $users = "")
     {
         $this->startTimer();
         $url = $this->server_url.'/channel.create';
         $postdata = "channel_name=".$channelName.'&users='.$users;
         //echo $postdata;
         $response = $this->doPOSTWithToken($url, $postdata);
         //var_dump($response);
         $this->stopTimer();
         return $response;

     }


 }