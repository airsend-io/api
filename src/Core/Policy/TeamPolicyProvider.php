<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Policy;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\UnsupportedPolicyException;
use CodeLathe\Core\Objects\Policy;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Policy\Policies\StorageQuota;
use CodeLathe\Core\Policy\Policies\TeamAnnouncements;
use CodeLathe\Core\Policy\Policies\TeamTagColor;
use CodeLathe\Core\Utility\ContainerFacade;
use Psr\Log\LoggerInterface;

class TeamPolicyProvider extends PolicyProvider
{
    /**
     * @var Team
     */
    protected $team;

    /**
     * @var DataController
     */
    protected $dataController;

    protected $logger;

    /**
     * List of supported policies by team
     * @var array
     */
    protected $supportedPolicies = [
        StorageQuota::class,
        TeamTagColor::class,
        TeamAnnouncements::class,
    ];

    public function __construct(Team $team)
    {
        $this->team = $team;
        $this->dataController = ContainerFacade::get(DataController::class);
        $this->logger = ContainerFacade::get(LoggerInterface::class);
    }

    /**
     * @return array
     */
    public function getSupportedPolicies(): array
    {
        return $this->supportedPolicies;
    }

    /**
     * @param string $key
     * @param string $value
     * @return mixed
     */
    public function setPolicyForKey(string $key, string $value): bool
    {
        if (empty($policy = $this->dataController->getPolicyTeam($this->team->getId(), $key))) {
            $policy = Policy::createTeamPolicy($this->team->getId(), $key, $value);
            return $this->dataController->createPolicy($policy);
        }
        $policy->setPolicyValue($value);
        return $this->dataController->updatePolicy($policy);
    }

    /**
     * @param string $key
     * @return Policy
     */
    public function getPolicyForKey(string $key): ?Policy
    {
        return $this->dataController->getPolicyTeam($this->team->getId(), $key);
    }
}