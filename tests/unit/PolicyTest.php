<?php

use Codeception\Test\Unit;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Policy\PolicyTypes;
use CodeLathe\Core\Policy\PolicyManager;

defined('CL_AS_ROOT_DIR') or define('CL_AS_ROOT_DIR', realpath(__DIR__ . '/../..'));

/**
 * Class PolicyTest
 * @group policy
 */

class PolicyTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $container;

    protected $userOps;

    protected $dataController;

    /**
     * @var PolicyManager
     */
    protected $policyMgr;

    protected $user;
    /**
     * @throws Exception
     */
    protected function _before()
    {
        $configRegistry = new ConfigRegistry();
        $containerIniter = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Init.php';
        $this->container = $containerIniter($configRegistry);
        $this->userOps = $this->container->get(UserOperations::class);
        $this->dataController = $this->container->get(DataController::class);
        $this->policyMgr = $this->container->get(PolicyManager::class);

        if (empty($this->user = $this->dataController->getUserByEmail('unittest1@test.com'))) {
            $this->user = $this->userOps->createUser('unittest1@test.com', '', 'password', 'Test User 1',
                User::ACCOUNT_STATUS_ACTIVE,
                User::USER_ROLE_EDITOR,
                User::APPROVAL_STATUS_APPROVED, false);
        }

    }

    protected function _after()
    {
    }


    public function testGetAllTeamPolicies()
    {
        $team = $this->dataController->getDefaultTeamForUser($this->user->getId());

        $teamPolices = $this->policyMgr->getPolicies($team);

        $this->assertNotCount(0, $teamPolices);

        $this->assertArrayHasKey('STORAGE_QUOTA_IN_GB', $teamPolices);

        $this->assertEquals('100', $teamPolices['STORAGE_QUOTA_IN_GB']);
    }

    public function testGetTeamPolicyForKey()
    {
        $team = $this->dataController->getDefaultTeamForUser($this->user->getId());

        $quota = $this->policyMgr->getPolicyValue($team, PolicyTypes::STORAGE_QUOTA_IN_GB);

        $this->assertEquals('100', $quota);
    }



}