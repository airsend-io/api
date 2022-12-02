<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Authorization;

use CodeLathe\Core\Authorization\Gates\AbstractGate;
use CodeLathe\Core\Authorization\Gates\ChannelGate;
use CodeLathe\Core\Authorization\Gates\FilesystemGate;
use CodeLathe\Core\Authorization\Gates\TeamGate;
use CodeLathe\Core\Exception\GateActionNotFoundException;
use CodeLathe\Core\Exception\GateNotFoundException;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TranslatedPath;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;

class AuthorizationHandler
{

    /**
     * @var string[]
     */
    protected $gates = [];

    public function __construct()
    {
        $this->registerGates();
    }

    public function registerGates()
    {
        // here we link objects and gates
        $this->gates = [
            TranslatedPath::class => FilesystemGate::class,
            Channel::class => ChannelGate::class,
            Team::class => TeamGate::class,
        ];
    }

    /**
     * @param User $user
     * @param $resource
     * @param string $action
     * @return bool
     * @throws GateNotFoundException
     * @throws GateActionNotFoundException
     */
    public function authorize(User $user, $resource, string $action): bool
    {

        // find the gate class
        $gateKey = $resource;
        if (is_object($gateKey)) {
            $gateKey = get_class($gateKey);
        }

        $gateClass = $this->gates[$gateKey] ?? null;

        if ($gateClass === null) {
            throw new GateNotFoundException($gateKey);
        }

        // instantiate the class
        $gate = ContainerFacade::get($gateClass);

        if (!method_exists($gate, $action)) {
            throw new GateActionNotFoundException($gateClass, $action);
        }

        // block any access for users that are not approved
        if ($user->getApprovalStatus() == User::APPROVAL_STATUS_PENDING) {
            return false;
        }

        // .. check user account status
        if (in_array($user->getAccountStatus(), [
            User::ACCOUNT_STATUS_DELETED,
            User::ACCOUNT_STATUS_BLOCKED,
            User::ACCOUNT_STATUS_DISABLED,
        ])) {
                return false;
        }

        return $gate->before($user, $resource) ?? $gate->$action($user, $resource);

    }
}