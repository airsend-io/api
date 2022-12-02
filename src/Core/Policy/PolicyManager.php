<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Policy;


use CodeLathe\Core\Exception\UnconfiguredPolicyException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UnsupportedPolicyException;
use CodeLathe\Core\Exception\UnsupportedPolicyTypeException;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Policy\Policies\AbstractPolicy;
use CodeLathe\Service\Logger\LoggerService;
use Psr\Container\ContainerInterface;

class PolicyManager
{
    /**
     * @var LoggerService
     */
    protected $logger;

    protected $container;

    /**
     * @var PolicyProvider|null
     */
    protected $provider;

    /**
     * PolicyManager constructor.
     * @param LoggerService $logger
     * @param ContainerInterface $container
     */
    public function __construct (LoggerService $logger,
                                 ContainerInterface $container)
    {
        $this->logger = $logger;
        $this->container = $container;
        $this->provider = null;
    }

    /**
     * @param $entity
     * @return PolicyProvider
     * @throws UnknownPolicyEntityException
     */
    protected function getProvider($entity): PolicyProvider
    {
        if ($entity instanceof Channel) {
            return new ChannelPolicyProvider($entity);
        } elseif ($entity instanceof Team) {
            return new TeamPolicyProvider($entity);
        } elseif ($entity instanceof User) {
            return new UserPolicyProvider($entity);
        } else {
            $this->logger->error(get_class($entity) . ' is not supported in policy system');
            throw new UnknownPolicyEntityException(get_class($entity) . ' is not supported in policy system');
        }
    }

    /**
     *
     * Returns all supported policies and their value in an associated array
     *
     * @param $entity can be User, Team or Channel
     * @return array
     * @throws UnknownPolicyEntityException
     */
    public function getPolicies($entity): array
    {
        $provider = $this->getProvider($entity);

        $policies = [];
        foreach ($provider->getSupportedPolicies() as $policyClass) {
            $policies[$policyClass] = $provider->getPolicyValue($policyClass);
        }

        return $policies;
    }

    /**
     * This contains the business logic of calculating effective permission for a key
     *
     * @param Team|User|Channel $entity
     * @param string $policyClass
     * @return mixed return the type defined on the policy class
     * @throws UnconfiguredPolicyException
     * @throws UnknownPolicyEntityException
     * @throws UnsupportedPolicyException
     * @throws UnsupportedPolicyTypeException
     */
    public function getPolicyValue($entity, string $policyClass)
    {

        // TODO - Entity can be an array to search on multiple layers, and get the most refined one?

        $provider = $this->getProvider($entity);

        // Depending on key type that can have multiple layers Team->Channel->User
        // the final effective value should be calculated

        return $provider->getPolicyValue($policyClass);
    }


    /**
     * @param $entity
     * @param string $policyClass
     * @param string $value
     * @return bool
     * @throws UnknownPolicyEntityException
     */
    public function setPolicyValue($entity, string $policyClass, string $value): void
    {
        /** @var AbstractPolicy $policy */
        $policy = new $policyClass($value);

        $provider = $this->getProvider($entity);

        $provider->setPolicyValue($policyClass, $policy->getRawValue());
    }

}