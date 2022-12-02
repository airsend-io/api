<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects\OAuth;

use League\OAuth2\Server\Entities\UserEntityInterface;

class User implements UserEntityInterface
{

    /**
     * @return mixed|void
     */
    public function getIdentifier()
    {
        // TODO: Implement getIdentifier() method.
    }
}