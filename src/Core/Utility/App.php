<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Utility;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Exception\SecurityException;

class App
{
    protected $configRegistry;
    public function __construct(ConfigRegistry $configRegistry)
    {
        $this->configRegistry = $configRegistry;
    }

    public  function version()
    {
        $baseversion = $this->configRegistry['/app/version'];
        $version = '0';
        if (SafeFile::file_exists(CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources'.DIRECTORY_SEPARATOR.'dev'.DIRECTORY_SEPARATOR.'BUILDVERSION'))
            $version = Safefile::file_get_contents(CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR .'resources'.DIRECTORY_SEPARATOR. 'dev'.DIRECTORY_SEPARATOR. 'BUILDVERSION');

        return $baseversion.$version;
    }
}