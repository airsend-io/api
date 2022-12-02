<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Policy;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\UnsupportedPolicyException;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Policy;
use CodeLathe\Core\Utility\ContainerFacade;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ChannelPolicyProvider extends PolicyProvider
{

    protected $channel;

    protected $dataController;

    protected $logger;

    /**
     * List of supported policies by team
     * @var array
     */
    protected $supportedPolicies = [
    ];

    /**
     * ChannelPolicyManager constructor.
     * @param Channel $channel
     */
    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
        $this->dataController = ContainerFacade::get(DataController::class);
        $this->logger = ContainerFacade::get(LoggerInterface::class);
    }

    /**
     * Return one dimensional array of supported policy list
     */
    public function getSupportedPolicies() : array
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

        if (empty($policy = $this->dataController->getPolicyTeam($this->channel->getId(), $key))) {
            $policy = Policy::createChannelPolicy($this->channel->getId(), $key, $value);
            return $this->dataController->createPolicy($policy);
        }
        $policy->setPolicyValue($value);
        return $this->dataController->updatePolicy($policy);
    }

    /**
     * @param string $key
     * @return Policy
     */
    public function getPolicyForKey (string $key): ?Policy
    {
        return $this->dataController->getPolicyChannel($this->channel->getId(), $key);
    }
}