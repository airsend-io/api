<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Admin;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Utility\JsonOutput;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Request as Request;

class AdminBiManager extends ManagerBase
{

    protected $dataController;

    protected $cubes = [
        'engaged_users'
    ];

    public function __construct(DataController $dataController)
    {
        $this->dataController = $dataController;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException
     */
    public function cubes(Request $request, Response $response): Response
    {
        return JsonOutput::success(200)->withContent('cubes', $this->cubes)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws \CodeLathe\Core\Exception\InvalidFailedHttpCodeException
     */
    public function cubeData(Request $request, Response $response, array $args = []): Response
    {
        $cube = $args['cube'] ?? null;
        if (!$cube === null || !in_array($cube, $this->cubes)) {
            return JsonOutput::error('Invalid cube ID', 400)->write($response);
        }
        return JsonOutput::success()->withContent('cube', $this->dataController->getCube($cube))->write($response);
    }

    /**
     * @inheritDoc
     */
    protected function dataController(): DataController
    {
        return $this->dataController;
    }
}