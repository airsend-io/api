<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Realtime;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidRequest;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\Files\FileController;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ChannelCreatedEvent;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\Path;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\JSONSerializableArray;
use CodeLathe\Core\Utility\RequestValidator;
use Exception;
use Firebase\JWT\JWT;
use PHPUnit\Util\Log\JSON;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class RtmManager extends ManagerBase
{
    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var RtmOperations
     */
    protected $rtmOps;


    /**
     * ChannelManager constructor.
     * @param LoggerInterface $logger
     * @param RtmOperations $rtmOps
     * @param DataController $dataController
     */
    public function __construct(LoggerInterface $logger,
                                RtmOperations $rtmOps,
                                DataController $dataController
                                )
    {
        $this->logger = $logger;
        $this->rtmOps = $rtmOps;
        $this->dataController = $dataController;
    }

    /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }


    /******************************************************************************************************************/
    //  END POINTS
    /******************************************************************************************************************/

    /**
     * Get information about a channel
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function connect(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getQueryParams();
        $ep = '';
        if (isset($params['rtm_endpoint'])) {
            $ep = $params['rtm_endpoint'];
        }
        try {
            $rtmObj = $this->rtmOps->getRtmResponse($user, $request->getAttribute('auth'), $ep);
        } catch (Exception $e) {
            $this->logger->error(__FUNCTION__ . " Failed generating rtm tokens : ". $e->getMessage());
            return JsonOutput::error(" Failed generating rtm tokens : ". $e->getMessage(), 500)->write($response);
        }

        // Return the channel object
        return JsonOutput::success()->withContent('rtm', $rtmObj)->write($response);
    }

}