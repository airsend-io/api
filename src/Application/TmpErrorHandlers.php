<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Temporary Error Handlers and Exception Handlers until Slim Framework Error Handler takes over
 * Without these, if there are errors in the route setup or middleware there might be no proper errors
 * during development.
 */

function error_log_handler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting, so let it fall
        // through to the standard PHP error handler
        return false;
    }

    switch ($errno) {
        case E_USER_ERROR:
            echo "<b>INTERNAL SERVER ERROR:</b> [$errno] $errstr<br />\n";
            echo "  Fatal error on line $errline in file $errfile";
            exit(1);
            break;

        case E_USER_WARNING:
            echo "<b>INTERNAL SERVER ERROR</b> [$errno] $errstr<br />\n";
            break;

        case E_USER_NOTICE:
            echo "<b>INTERNAL SERVER ERROR</b> [$errno] $errstr<br />\n";
            break;

        default:
            echo "<b>INTERNAL SERVER ERROR</b>: Unknown error type: [$errno] $errstr<br />\n";
            break;
    }

    /* Don't execute PHP internal error handler */
    return true;
}
$old_error_handler = set_error_handler('error_log_handler');

function exception_handler($exception) {
    echo "<b>INTERNAL SERVER ERROR</b>: ". $exception->getMessage()."<br />\n";
}
set_exception_handler('exception_handler');