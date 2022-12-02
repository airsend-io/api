<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command;

use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\User;

/**
 * Describes a Service instance.
 */
interface CommandInterface
{

    /**
     * @param string $command
     * @param string $params
     * @return bool
     */
    public function validateSignature(string $command, string $params): bool;

    /**
     * @return array
     */
    public function getUiSignature();

    /**
     * @param Channel $channel
     * @param User $user
     * @return mixed
     */
    public function setUp(Channel $channel, User $user);

    /**
     * Executes the command
     *
     * @return mixed
     */
    public function handle(): ?array;

}