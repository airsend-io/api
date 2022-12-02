<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Validators\InputValidator;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;
use function FastRoute\TestFixtures\empty_options_cached;

/**
 * Class RequestValidator
 * @package CodeLathe\Core\Utility
 */
abstract class RequestValidator
{
    /**
     * @param array $parameterNames Names of parameters to be validated
     * @param array $params Actual parameter to validate
     * @param ResponseInterface $response Response object to return
     * @return bool
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     */
    public static function validateRequest(array $parameterNames, ?array &$params, ResponseInterface &$response, array $fieldNameMapping = null): bool
    {
        $params = $params ?? [];
        // Validate the request
        try {
            $iv = new InputValidator();
            $iv->setupRules($parameterNames, $fieldNameMapping);
            $output = $iv->run($params);
            if ($output === false) {
                $errors = $iv->get_errors_array();
                ContainerFacade::get(LoggerInterface::class)->info("VALIDATION FAILURE " . print_r($errors,true));

                $errorMsg = '';
                foreach ($errors as $key=>$value) {
                    if (!empty($errorMsg)) {
                        $errorMsg .= '<br>';
                    }

                    $errorMsg .= $value;
                }
                if (empty($errorMsg)) {
                    $errorMsg = 'Input Validation Failed';
                }

                $output = JsonOutput::error($errorMsg, 422);
                foreach ($errors as $key => $value) {
                    $output->withContent($key, $value);
                }
                $response = $output->write($response);
                return false;
            }
            $params = $output;
            return true;
        } catch (InvalidFailedHttpCodeException $e) {
            throw $e;
        } catch (Exception $e) {
            // ... NOTE: Rule "mismatch" does not have an error message might be emitted if you are passsing extra params
            // ... but your validation fields list doesn't consider that and you also have other errors
            throw new ValidationErrorException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    /**
     *
     * This is to validate an array set data without HTTP message context
     * @param array $parameterNames
     * @param array $params
     * @param array|null $fieldNameMapping
     * @return bool
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     */
    public static function validateArray(array $parameterNames, array &$params, array $fieldNameMapping = null): bool
    {
        // Validate the request
        try {
            $iv = new InputValidator();
            $iv->setupRules($parameterNames, $fieldNameMapping);
            $output = $iv->run($params, true);
            if ($output === false) {
                $errors = $iv->get_errors_array();
                $output = JsonOutput::error("Validation Failed", 422);
                foreach ($errors as $key => $value) {
                    $output->withContent($key, $value);
                }
                return false;
            }
            $params = $output;
            return true;
        } catch (InvalidFailedHttpCodeException $e) {
            throw $e;
        } catch (Exception $e) {
            LoggerFacade::error("Validation error {$e->getMessage()}. Params: " . print_r($params, true));
            return false;
        }
    }

}