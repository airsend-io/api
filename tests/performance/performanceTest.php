<?php

/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

require_once ('airSendAPI.php');

class PerformanceTest
{
    protected $url = "http://localhost/api/v1";

    public function getPerformance()
    {
        chdir("../../../../");
        exec ('docker-compose exec api composer run initdb');

        $airSendAPI = new AirSendAPI($this->url);
        $name = "TestPerf";
        $email = "testperf4@airsendmail.com";
        $phone = "";
        $password = "passlock123";

        $record = $airSendAPI->createUser($name, $email, $phone, $password);
        $obj = json_decode($record);
        $status = $obj->meta->ok;
        if($status == true)
        {
            $createUserTime = $airSendAPI->getLastRunTime();
            echo nl2br("\n User Create Time : " . $createUserTime. 's  ');
        }

        $record = $airSendAPI->userLogin($email, $password);
        $obj = json_decode($record);
        $user_id = $obj->user->id;
        $status = $obj->meta->ok;
        if($status == true)
        {
            $userLoginTime = $airSendAPI->getLastRunTime();
            echo nl2br("\n User Login Time : " . $userLoginTime. 's  ');
        }


        $record = $airSendAPI->userInfo('admin@airsend.io', '', $user_id);
        $obj = json_decode($record);
        $status = $obj->meta->ok;
        if($status == true)
        {
            $userInfoTime = $airSendAPI->getLastRunTime();
            echo nl2br("\n User Info Time  : " . $userInfoTime. 's  ');
        }

        $phone = "919677840540";
        $record = $airSendAPI->userProfileSet($email,$phone,$name);
        $obj = json_decode($record);
        $status = $obj->meta->ok;
        if($status == true)
        {
            $userProfileSet = $airSendAPI->getLastRunTime();
            echo nl2br("\n User Profile Set Time  : " . $userProfileSet. 's  ');
        }

        $channelName = "testChannel";
        $email2 = "testperf3@airsendmail.com";
        $users = $email.','.$email2;
        $record = $airSendAPI->channelCreate($channelName,$users);
        //var_dump($record);
        $obj = json_decode($record);
        $status = $obj->meta->ok;
        if($status == true)
        {
            $channelCreateTime = $airSendAPI->getLastRunTime();
            echo nl2br("\n Channel Create Time  : " . $channelCreateTime . 's  ');
        }





    }




}

$perf = new PerformanceTest();
$perf->getPerformance();