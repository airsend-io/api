<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\PublicHashHandlers;

use CodeLathe\Core\Data\DataController;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractPublicHashHandler
{

    protected $dataController;

    public function __construct(DataController $dataController)
    {
        $this->dataController = $dataController;
    }

    protected function splitRoute(ServerRequestInterface $request): ?string
    {
        $path = trim($request->getUri()->getPath());
        if (!preg_match('#^/api/v[0-9]+/([^?]+)#', $path, $matches)) {
            return null;
        }
        return $matches[1];
    }

    abstract public function allow(ServerRequestInterface $request, string $resourceId): bool;
}