<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command\Commands;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Managers\Call\CallOperations;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Command\CommandInterface;
use CodeLathe\Service\Command\CommandTrait;

/**
 * Describes a Service instance.
 */
class CallCommand implements CommandInterface
{

    use CommandTrait;


    /**
     * @var ConfigRegistry
     */
    protected $config;
    /**
     * @var CallOperations
     */
    private $callOps;

    public function __construct(CallOperations $callOperations, ConfigRegistry $config)
    {
        $this->callOps = $callOperations;
        $this->config =  $config;
    }

    protected function signature(): string
    {
        return 'call';
    }

    /**
     * @return array|null
     * @throws \CodeLathe\Core\Exception\CallOpException
     * @throws \CodeLathe\Core\Exception\ChatOpException
     * @throws \CodeLathe\Core\Exception\DatabaseException
     */
    public function handle(): ?array
    {
        $call = $this->callOps->createCall($this->user, $this->channel);
        return ContainerFacade::get(NormalizedObjectFactory::class)->normalizedObject($call)->getArray();
    }

    public function getUiSignature()
    {
        return $this->defaultUiSignature('call', false, ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR_WIKI);
    }
}