<?php

function output($result, $message) {

    global $quiet;

    if ($quiet ?? false) {
        return;
    }

    if ($result !== false) {
        echo $message . addPeriods(60 - strlen($message)) . "DONE \n";
    }
    else {
        echo $message . addPeriods(60 - strlen($message)) . "FAIL \n";
        exit(1);
    }

}

function addPeriods($count) {
    $str = ".";
    for($i=0; $i<$count; $i++){
        $str .= ".";
    }
    return $str;
}
