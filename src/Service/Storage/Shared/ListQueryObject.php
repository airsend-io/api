<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Shared;


/**
 * Class ListQueryObject
 * @package CodeLathe\Service\Storage\Shared
 */
class ListQueryObject
{
    private $queryParams;

    /**
     * ListQueryObject constructor.
     * @param array $queryParams
     */
    public function __construct(array $queryParams)
    {
        $this->queryParams = $queryParams;
    }

    /**
     * @return string|null
     */
    public function getParentPath(): ?string
    {
        return $this->queryParams['parentpath']??NULL;
    }

    /**
     * @return bool
     */
    public function doRecursive() : bool
    {
        return $this->queryParams['recursive']??TRUE;
    }

    /**
     * @return bool
     */
    public function leafFirst() : bool
    {
        return $this->queryParams['leafFirst']??TRUE;
    }

    /**
     * @return int
     */
    public function getOffset() : int
    {
        return $this->queryParams['offset']??0;
    }

    /**
     * @return int
     */
    public function getLimit() : int
    {
        return $this->queryParams['limit']??-1;
    }

}