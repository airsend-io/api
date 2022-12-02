<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class NoTeamSeatsAvailableException extends ASException
{

    /**
     * @var int
     */
    protected $used;

    /**
     * @var int
     */
    protected $available;

    public function __construct(int $used, int $available)
    {
        parent::__construct();
        $this->used = $used;
        $this->available = $available;
    }

    /**
     * @return int
     */
    public function getUsed(): int
    {
        return $this->used;
    }

    /**
     * @return int
     */
    public function getAvailable(): int
    {
        return $this->available;
    }
}