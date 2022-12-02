<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects\OAuth;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\Convert;
use CodeLathe\Core\Utility\Json;
use DateTimeImmutable;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use Psr\Log\LoggerInterface;

class AccessToken implements AccessTokenEntityInterface
{

    use AccessTokenTrait, EntityTrait, TokenEntityTrait;

}
