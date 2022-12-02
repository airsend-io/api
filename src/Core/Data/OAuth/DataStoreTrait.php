<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\OAuth;

use CodeLathe\Service\Database\DatabaseService;

trait DataStoreTrait
{

    /**
     * @var DatabaseService
     */
    protected $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

}
