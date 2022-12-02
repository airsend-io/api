<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Policy;


use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\UnsupportedPolicyException;
use CodeLathe\Core\Objects\Policy;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Policy\Policies\AvailableTeamSeats;
use CodeLathe\Core\Utility\ContainerFacade;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class UserPolicyProvider extends PolicyProvider
{

    /**
     * @var User
     */
    protected $user;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $supportedPolicies = [
        AvailableTeamSeats::class,
    ];

    /**
     * ChannelPolicyManager constructor.
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->dataController = ContainerFacade::get(DataController::class);
        $this->logger = ContainerFacade::get(LoggerInterface::class);
    }

    /**
     * Return one dimensional array of supported policy list
     */
    public function getSupportedPolicies () : array
    {
        return $this->supportedPolicies;
    }


    /**
     * @param string $key
     * @param $value
     * @return mixed
     * @throws UnsupportedPolicyException
     */
    public function setPolicyForKey (string $key, string $value) : bool
    {
        if (empty($policy = $this->dataController->getPolicyTeam($this->user->getId(), $key))) {
            $policy = Policy::createUserPolicy($this->user->getId(), $key, $value);
            return $this->dataController->createPolicy($policy);
        }
        $policy->setPolicyValue($value);
        return $this->dataController->updatePolicy($policy);

    }

    /**
     * @param string $key
     * @return Policy
     * @throws DatabaseException
     */
    public function getPolicyForKey(string $key): ?Policy
    {
        return $this->dataController->getPolicyUser($this->user->getId(), $key);
    }
}