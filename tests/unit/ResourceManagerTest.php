<?php

use Codeception\Test\Unit;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Objects\ChannelPath;
use CodeLathe\Core\Objects\User;

defined('CL_AS_ROOT_DIR') or define('CL_AS_ROOT_DIR', realpath(__DIR__ . '/../..'));

class ResourceManagerTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @throws Exception
     */
    protected function _before()
    {

        $configRegistry = new ConfigRegistry();
        $containerIniter = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Init.php';
        $this->container = $containerIniter($configRegistry);
        \CodeLathe\Core\Utility\ContainerFacade::setUp($this->container);

        // ... Create a random user
        $uo = $this->container->get(UserOperations::class);
        $this->user = $uo->createUser(time().'@gg.com','447700900899', 'password', 'UnitTest Man',
            User::ACCOUNT_STATUS_ACTIVE, User::USER_ROLE_EDITOR, User::APPROVAL_STATUS_APPROVED);

    }

    protected function _after()
    {
    }

    public function dataProvider()
    {
        return [
            ["/f/42"],
            ["/f/42/file"],
            ["/f/42/folder/file"],
            ["f/42", 'expectedException' => \CodeLathe\Core\Exception\UnknownResourceException::class],
            ["/f42", 'expectedException' => \CodeLathe\Core\Exception\UnknownResourceException::class],
            ["/r/random", 'expectedException' => \CodeLathe\Core\Exception\UnknownResourceException::class],
            ["something", 'expectedException' => \CodeLathe\Core\Exception\UnknownResourceException::class]
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testResourceConstruction(string $resource, $expectedException = null)
    {
        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $fileResource = ResourceManager::getResource($resource);
        $this->assertEquals($fileResource->getResourcePrefix(), "/f");
        $this->assertEquals($fileResource->getResourceIdentifier(), $resource);
    }

    public function testFileResourceConversion()
    {
        $dc = new DataController($this->container);

        $defaultTeamForUser = $dc->getDefaultTeamForUser($this->user->getId());
        $this->assertNotNull($defaultTeamForUser);
        $teamid = $defaultTeamForUser->getId();

        // .. Verify On New Channel Creation
        $co = $this->container->get(ChannelOperations::class);
        $channelName = "devfs".time();
        $channel = $co->createChannel($this->user, $channelName);

        $filePath = '/f/'.$teamid.'/Channels/'.$channelName."/".FileOperations::getChannelFilesName();

        $channelPaths = $dc->getChannelPathsByValue($filePath);
        $channelFilePath = '';
        foreach ($channelPaths as $channelPathData) {
            $channelPath = ChannelPath::withDBData($channelPathData);
            $associatedId = $channelPath->getId();
            $channelFilePath = '/cf/' . $associatedId;
            break;
        }
        $fileResource = ResourceManager::getResource($filePath);
        $this->assertEquals($fileResource->getResourceIdentifier(), $filePath);
        $channelFileInfoArray = array();
        $this->assertTrue($fileResource->getAssociatedChannelInfo($dc, $channelFileInfoArray));
        $this->assertEquals(1, count($channelFileInfoArray));
        $this->assertEquals((int)$channel->getId(), (int)$channelFileInfoArray[0]["id"]);
        $this->assertEquals($channelFilePath, $channelFileInfoArray[0]["channelpath"]);

        $chfileResource = ResourceManager::getResource($channelFilePath);
        $this->assertEquals($chfileResource->getResourceIdentifier(), $channelFilePath);
        $channelFileInfoArray = array();
        $this->assertTrue($chfileResource->getAssociatedChannelInfo($dc, $channelFileInfoArray));
        $this->assertEquals((int)$channel->getId(), (int)$channelFileInfoArray[0]["id"]);
        $this->assertEquals($channelFilePath, $channelFileInfoArray[0]["channelpath"]);
     }

    public function testFileResourceConversionChildPath()
    {
        $dc = new DataController($this->container);

        $defaultTeamForUser = $dc->getDefaultTeamForUser($this->user->getId());
        $this->assertNotNull($defaultTeamForUser);
        $teamid = $defaultTeamForUser->getId();

        // .. Verify On New Channel Creation
        $co = $this->container->get(ChannelOperations::class);
        $channelName = "devfs".time();
        $channel = $co->createChannel($this->user, $channelName);

        $basePath = '/f/'.$teamid.'/Channels/'.$channelName."/".FileOperations::getChannelFilesName();
        $filePath = $basePath.'/child';

        $channelPaths = $dc->getChannelPathsByValue($basePath);
        $channelFilePath = '';
        foreach ($channelPaths as $channelPathData) {
            $channelPath = ChannelPath::withDBData($channelPathData);
            $associatedId = $channelPath->getId();
            $channelFilePath = '/cf/' . $associatedId.'/child';
            break;
        }

        $fileResource = ResourceManager::getResource($filePath);
        $this->assertEquals($fileResource->getResourceIdentifier(), $filePath);
        $channelFileInfoArray = array();
        $this->assertTrue($fileResource->getAssociatedChannelInfo($dc, $channelFileInfoArray));
        $this->assertEquals(1, count($channelFileInfoArray));
        $this->assertEquals((int)$channel->getId(), (int)$channelFileInfoArray[0]["id"]);
        $this->assertEquals($channelFilePath, $channelFileInfoArray[0]["channelpath"]);

        $chfileResource = ResourceManager::getResource($channelFilePath);
        $this->assertEquals($chfileResource->getResourceIdentifier(), $channelFilePath);
        $channelFileInfoArray = array();
        $this->assertTrue($chfileResource->getAssociatedChannelInfo($dc, $channelFileInfoArray));
        $this->assertEquals((int)$channel->getId(), (int)$channelFileInfoArray[0]["id"]);
        $this->assertEquals($channelFilePath, $channelFileInfoArray[0]["channelpath"]);
    }

}