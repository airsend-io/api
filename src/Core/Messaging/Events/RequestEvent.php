<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use CodeLathe\Core\Utility\Auth;
use Psr\Http\Message\RequestInterface;

/**
 * This event is for tracking User login event
 */

class RequestEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'request';

    /**
     * @var array
     */
    protected $requestHeaders;

    /**
     * @var CarbonImmutable
     */
    protected $date;

    /**
     * @var int|null
     */
    protected $loggedUserId;

    /**
     * @var string
     */
    protected $uriPath;

    /**
     * @var int
     */
    protected $timeTaken;

    /**
     * @var string
     */
    protected $remoteIp;

    public function __construct(RequestInterface $request, string $remoteIp, int $timeTaken)
    {
        $this->requestHeaders = array_map(function ($item) {
            return $item[0] ?? '';
        }, $request->getHeaders());

        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');
        $this->loggedUserId = $auth->getUserId();

        $this->uriPath = $request->getUri()->getPath();

        $this->date = CarbonImmutable::now();

        $this->remoteIp = $remoteIp;

        $this->timeTaken = $timeTaken;
    }

    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId() : int
    {
        return 0;
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    static function eventName(): string
    {
        return self::NAME;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray(): array
    {
        return [];
    }

    public function getRequestHeaders(): array
    {
        return $this->requestHeaders;
    }

    public function getLoggedUserId(): ?int
    {
        return $this->loggedUserId;
    }

    public function getDate(): CarbonImmutable
    {
        return $this->date;
    }

    public function getUriPath(): string
    {
        return $this->uriPath;
    }

    public function getRemoteIp(): string
    {
        return $this->remoteIp;
    }

    public function getTimeTaken(): int
    {
        return $this->timeTaken;
    }
}
