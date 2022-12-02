<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Admin;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Data\MigrationController;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Utility\JsonOutput;
use PHPUnit\Util\Log\JSON;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Request as Request;

class MigrationManager extends ManagerBase
{
    protected $migrationController;

    protected $dataController;

    public function __construct(MigrationController $migration, DataController $dataController)
    {
        $this->migrationController = $migration;
        $this->dataController = $dataController;
    }

    /**
     * Derived class must give us the dataController
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }

    public function upgrade(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        if (!$this->migrationController->isUpgradeAvailable()){
            return JsonOutput::error("No Upgrade Available", 500)->write($response);
        }

        if (!$this->migrationController->upgrade()) {
            return JsonOutput::error("Database upgrade failed", 500)->write($response);
        }

        return JsonOutput::success(200)->write($response);
    }

    public function info(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $info = [];
        $info['latest_version'] = $this->migrationController->getLatestVersion();
        $info['current_version'] = $this->migrationController->getCurrentVersion();
        $info['upgrade_available'] = $this->migrationController->isUpgradeAvailable();

        return JsonOutput::success()->withContent('info', $info)->write($response);
    }

    public function list(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $versions = $this->migrationController->listVersions();

        return JsonOutput::success()->withContent('versions', $versions)->write($response);
    }
}