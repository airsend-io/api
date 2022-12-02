<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Search;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\UnknownUserException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\RequestValidator;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class SearchManager extends ManagerBase
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var SearchOperations
     */
    protected $searchOps;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * BackgroundDispatcher constructor.
     * @param LoggerInterface $logger
     * @param SearchOperations $searchOps
     * @param DataController $dc
     */
    public function __construct(LoggerInterface $logger, SearchOperations $searchOps, DataController $dc)
    {
        $this->logger = $logger;
        $this->searchOps = $searchOps;
        $this->dataController = $dc;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     */
    public function query(Request $request, Response $response): Response
    {

        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        if (!empty($params['scope'])) {
            $params['search_scope'] = $params['scope'];
            unset($params['scope']);
        }

        if (!empty($params['channel'])) {
            $params['search_channel'] = $params['channel'];
            unset($params['channel']);
        }

        if (!empty($params['limit'])) {
            $params['search_limit'] = $params['limit'];
            unset($params['limit']);
        }


        if (!RequestValidator::validateRequest(['query', 'search_scope', 'search_limit', 'search_channel'], $params, $response)) {
            return $response;
        }

        $query = $params['query'];
        $scope = $params['search_scope'] ?? null;
        $channelId = isset($params['search_channel']) ? (int)($params['search_channel']) : null;
        $limit = (int)($params['search_limit'] ?? 3); // limit defaults to 3
        $limit = $limit === 0 ? 3 : $limit;
        $limit = $limit > 100 ? 100 : $limit;

        $this->logger->info(__FUNCTION__ .": Limit=$limit Scope=$scope Query=$query");

        try {
            $responsePayload = $this->searchOps->search($loggedUser, $query, $limit, $channelId, $scope);
        } catch (NoNodesAvailableException $e) {
            $message = 'Search engine is down';
            $this->logger->critical($message);
            return JsonOutput::error($message, 500)->write($response);
        }

        return JsonOutput::success()->withContent('results', $responsePayload)->write($response);

    }

    protected function dataController(): DataController
    {
        return $this->dataController;
    }
}

