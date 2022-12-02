<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\DW\DimensionParsers;

use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Service\Database\DatabaseService;

class EndpointDimensionParser
{

    /**
     * A list of strings. Each string can be a full match for the endpoint or an regex (delimiter must always be /)
     */
    const UNPRODUCTIVE_ENDPOINTS = [
        'handshake',
        'u',
        '/^oauth\..+/',
        'user.login',
        'user.logout',
        'user.login.refresh',
        '/^internal\..+/',
        '/^system\..+/',
        '/^admin\..+/',
        '/^rtm\..+/'
    ];

    const DATA_CALLS = [
        'user.image.get',
        'user.profile.get',
        'channel.image.get'
    ];

    /**
     * @var string
     */
    protected $uriPath;

    public function __construct(string $uriPath)
    {
        $this->uriPath = $uriPath;
        $this->dbs = ContainerFacade::get(DatabaseService::class);
    }

    /**
     * Parses and save the dimension data to the database.
     * Returns the id of the inserted/found record
     *
     * @return array
     */
    public function parse(): array
    {

        $data = [];

        if (!preg_match('/^\/api\/v[0-9]+\/([^\/]+)/', $this->uriPath, $matches)) {
            return []; // skip invalid uri
        }

        $endpoint = $matches[1];

        $data['endpoint'] = $endpoint;

        [$prefix] = explode('.', $endpoint);
        $data['prefix'] = $prefix;

        // productive endpoints are those that are triggered by the user to input something into the system
        // the endpoints that are not just used as helpers to allow the system to work (like pings, handshakes, and stuff)
        $data['productive'] = $this->isProductive($endpoint);

        // data calls are calls that return data to the client, and should be cached on the client side
        $data['data_call'] = $this->isDataCall($endpoint);

        return $data;
    }

    protected function isProductive(string $endpoint): bool
    {
        foreach (static::UNPRODUCTIVE_ENDPOINTS as $pattern) {

            // if the pattern is a regex, use preg_match
            if (preg_match('/^\//', $pattern)) {
                if (preg_match($pattern, $endpoint)) {
                    return false;
                }
            } else {
                if ($pattern === $endpoint) {
                    return false;
                }
            }
        }

        // no match, so it's productive
        return true;
    }

    protected function isDataCall(string $endpoint): bool
    {
        foreach (static::DATA_CALLS as $pattern) {

            // if the pattern is a regex, use preg_match
            if (preg_match('/^\//', $pattern)) {
                if (preg_match($pattern, $endpoint)) {
                    return true;
                }
            } else {
                if ($pattern === $endpoint) {
                    return true;
                }
            }
        }

        // no match, so it's productive
        return false;
    }

}