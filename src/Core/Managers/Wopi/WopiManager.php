<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Wopi;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\BadResourceTranslationException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Serializers\JSONSerializer;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Service\Logger\LoggerService;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Request as Request;

class WopiManager extends ManagerBase
{
    /**
     * @var LoggerService
     */
    protected $logger;

    /**
     * @var WopiOperations
     */
    protected $wopiOps;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var WopiCallback
     */
    protected $wopiCallback;

    /**
     * @var FileOperations
     */
    protected $fOps;

    /**
     * ChatManager constructor.
     * @param DataController $dataController
     * @param WopiOperations $wopiOps
     * @param WopiCallback $wopiCallback
     * @param LoggerService $logger
     */
    public function __construct(DataController $dataController,
                                WopiOperations $wopiOps,
                                FileOperations $fOps,
                                WopiCallback $wopiCallback,
                                LoggerService $logger)
    {
        $this->logger = $logger;
        $this->wopiOps = $wopiOps;
        $this->dataController = $dataController;
        $this->wopiCallback = $wopiCallback;
        $this->fOps = $fOps;
    }

    /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }

    /**
     *
     * Edit a office document
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     */
    public function edit (Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        // Validate request
        if (!RequestValidator::validateRequest(['fspath'], $params, $response)) {
            return $response;
        }

        $this->logger->info(__FUNCTION__ . " Params=" . print_r($params,true));

        $fspath = $params['fspath'];
        $token = $params['token'];

        try {
            if (!$this->wopiOps->accessAllowed($fspath, $user, true)) {
                return JsonOutput::error("No access to path", 401)->write($response);
            }

            $htmlRespStr = $this->wopiOps->getWopiEndpointForFile($user, $params['fspath'], $request->getAttribute('auth'));
            $response->getBody()->write($htmlRespStr);
            return $response->withHeader('Content-type','text/html;charset=UTF-8')->withStatus(200);
        }
        catch(BadResourceTranslationException $ex) {
            return JsonOutput::error($ex->getMessage(), 404)->write($response);
        }
        catch(UnknownResourceException $ex) {
            return JsonOutput::error($ex->getMessage(), 404)->write($response);
        }
        catch(ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }
    }

    /**
     *
     * View a office document
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     */
    public function view (Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        // Validate request
        if (!RequestValidator::validateRequest(['fspath'], $params, $response)) {
            return $response;
        }

        $fspath = $params['fspath'];
        $token = $params['token'];
        //$this->logger->info(__FUNCTION__ . " Params=" . print_r($params,true));
        try {

            if (!$this->wopiOps->accessAllowed($fspath, $user, false)) {
                return JsonOutput::error("No access to path", 401)->write($response);
            }

            $htmlRespStr = $this->wopiOps->getWopiEndpointForFile($user, $params['fspath'], $request->getAttribute('auth'),'view');
            $response->getBody()->write($htmlRespStr);
            return $response->withHeader('Content-type','text/html;charset=UTF-8')->withStatus(200);
        }
        catch(BadResourceTranslationException $ex) {
            return JsonOutput::error($ex->getMessage(), 404)->write($response);
        }
        catch(UnknownResourceException $ex) {
            return JsonOutput::error($ex->getMessage(), 404)->write($response);
        }

        catch(ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }
    }

    /**
     *
     * Entry point for Microsoft O365 server to interact with AirSend
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws InvalidFailedHttpCodeException
     */
    public function access (Request $request, Response $response, array $args): Response
    {
        $this->logger->info(__FUNCTION__ . ": Got Microsoft Call! ");
        if (!isset($args['path_token'])) {
            return (new JSONSerializer(false))->write($response);
        }
        try {
            // path_token now contains the path for this entry
            $claims = (array)JWT::decode($args['path_token'],WopiOperations::JWT_KEY,  array('HS256'));

            //$this->logger->info(__FUNCTION__ . " Claims=" . print_r($claims,true));
            if (empty($claims['path'])) {
                $this->logger->error(__FUNCTION__ . "Path not found in claim. Invalid Request");
                return JsonOutput::error('Invalid Request', 400)->write($response);
            }
            // At this point, we are ready to process this request.
            $this->wopiCallback->route($request, $response, $claims, $args);
            return $response->withStatus(200);
        }
        catch(ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }
        catch(\Exception $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        }
        return JsonOutput::error('Generic Error', 400)->write($response);
    }

}