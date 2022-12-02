<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects\OAuth;

use JsonSerializable;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class Client implements ClientEntityInterface, JsonSerializable
{

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var
     */
    protected $description;

    /**
     * @var int
     */
    protected $ownerId;

    /**
     * @var string
     */
    protected $redirectUri;

    /**
     * @var string
     */
    protected $secret;

    /**
     * @var int
     */
    protected $grantType;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @param array $data
     * @return static
     */
    public static function withDbData(array $data): self
    {
        $instance = new static();
        $instance->id = $data['id'];
        $instance->name = $data['name'];
        $instance->description = $data['description'];
        $instance->ownerId = (int)$data['owner_id'];
        $instance->redirectUri = explode(',', $data['redirect'] ?? '');
        $instance->secret = $data['secret'];
        $instance->grantType = $data['grant_type'];
        $instance->active = $data['active'];

        return $instance;
    }

    /**
     * @return string|void
     */
    public function getIdentifier()
    {
        return $this->id;
    }

    /**
     * @return string|void
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string|string[]|void
     */
    public function getRedirectUri()
    {
        return $this->redirectUri;
    }

    /**
     * @return bool|void
     */
    public function isConfidential()
    {
        return true;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function jsonSerialize()
    {
        extract(get_object_vars($this));
        return compact('id', 'secret', 'grantType', 'name', 'description', 'redirectUri', 'active');
    }
}
