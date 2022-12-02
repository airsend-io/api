<?php

use Codeception\Test\Unit;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Files\FileController;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Objects\ChannelFileResource;
use CodeLathe\Core\Objects\ChannelPath;
use CodeLathe\Core\Objects\File;
use CodeLathe\Core\Objects\FileResource;
use CodeLathe\Core\Objects\Folder;
use CodeLathe\Core\Objects\FolderProps;
use CodeLathe\Core\Objects\User;
use Psr\Log\LoggerInterface;

defined('CL_AS_ROOT_DIR') or define('CL_AS_ROOT_DIR', realpath(__DIR__ . '/../..'));

class FileOperationsTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $container;

    protected $user;

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

    private function getUploadFile()
    {
        return CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'wiki'.DIRECTORY_SEPARATOR.'index.md';
    }

    public function testFileOperationsTeam()
    {
        $dc = new DataController($this->container);

        // .. Verify On New Team Creation
        $defaultTeamForUser = $dc->getDefaultTeamForUser($this->user->getId());
        $this->assertNotNull($defaultTeamForUser);
        $teamid = $defaultTeamForUser->getId();

        $fo = $this->container->get(FileOperations::class);
        $obj = $fo->info("/f/".$teamid, $this->user->getId());
        $this->assertInstanceOf(Folder::class, $obj);
        $this->assertEquals($obj->getName(), $teamid);
        $this->assertEquals($obj->getExtension(), "");
        $this->assertEquals($obj->getType(), "folder");

        $paths = array();
        $this->assertTrue($fo->getUserRoots($this->user, $paths));
        $this->assertTrue(count($paths) == 1); // For now only single team roots
        $this->assertInstanceOf(FileResource::class, $paths[0]);
        $this->assertEquals($paths[0]->getResourceIdentifier(), "/f/".$teamid );
    }

    public function testFileOperationsChannel()
    {
        $dc = new DataController($this->container);

        $defaultTeamForUser = $dc->getDefaultTeamForUser($this->user->getId());
        $this->assertNotNull($defaultTeamForUser);
        $teamid = $defaultTeamForUser->getId();

        // .. Verify On New Channel Creation
        $co = $this->container->get(ChannelOperations::class);
        $channelName = "devfs".time();
        $channel = $co->createChannel($this->user, $channelName);

        // ... Verify if Files and Wiki Folders are created
        $fo = $this->container->get(FileOperations::class);
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName(), $this->user->getId());
        $this->assertInstanceOf(Folder::class, $obj);
        $this->assertEquals($obj->getName(), FileOperations::getChannelFilesName());
        $this->assertEquals($obj->getExtension(), "");
        $this->assertEquals($obj->getType(), "folder");

        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName(), $this->user->getId());
        $this->assertInstanceOf(Folder::class, $obj);
        $this->assertEquals($obj->getName(), FileOperations::getChannelWikiName());
        $this->assertEquals($obj->getExtension(), "");
        $this->assertEquals($obj->getType(), "folder");

        // .... Get Channel Roots
        $paths = array();
        $props = new FolderProps();
        $this->assertTrue($fo->getChannelRoots($channel, $paths, $props));
        $this->assertTrue(count($paths) == 2); // For now we pass both files and wiki roots for a channel might change
        $this->assertInstanceOf(ChannelFileResource::class, $paths[0]);

        $dbfilepaths = array();
        $dbwikipaths = array();
        $channelPaths = $dc->getChannelPathsByChannelId((int)$channel->getId());
        foreach($channelPaths as $channelPathData) {
            $channelPath = ChannelPath::withDBData($channelPathData);
            // ... Convert the file path to channel path
            if ($channelPath->getPathType() == ChannelPath::CHANNEL_PATH_TYPE_FILE)
            {
                $dbfilepaths[] = new ChannelFileResource("/cf/".$channelPath->getId());
            }
            else if ($channelPath->getPathType() == ChannelPath::CHANNEL_PATH_TYPE_WIKI)
            {
                $dbwikipaths[] = new ChannelFileResource("/wf/".$channelPath->getId());
            }
        }

        $this->assertTrue(count($dbfilepaths) == 1);
        $this->assertTrue(count($dbwikipaths) == 1);
        $this->assertEquals($paths[0]->getResourceIdentifier(), $dbfilepaths[0]->getResourceIdentifier());
        $this->assertEquals($paths[1]->getResourceIdentifier(), $dbwikipaths[0]->getResourceIdentifier());

        // ... Verify Wiki Main Path exists
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index.md", $this->user->getId());
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);
    }

    public function testFileOperationsBasicFileOps()
    {
        $dc = new DataController($this->container);

        $defaultTeamForUser = $dc->getDefaultTeamForUser($this->user->getId());
        $this->assertNotNull($defaultTeamForUser);
        $teamid = $defaultTeamForUser->getId();

        // .. Verify On New Channel Creation
        $co = $this->container->get(ChannelOperations::class);
        $channelName = "devfs".time();
        $channel = $co->createChannel($this->user, $channelName);

        // ... Verify if Files and Wiki Folders are created
        $fo = $this->container->get(FileOperations::class);
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName(), $this->user->getId());
        $this->assertInstanceOf(Folder::class, $obj);
        $this->assertEquals($obj->getName(), FileOperations::getChannelFilesName());
        $this->assertEquals($obj->getExtension(), "");
        $this->assertEquals($obj->getType(), "folder");

        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/wiki", $this->user->getId());
        $this->assertInstanceOf(Folder::class, $obj);
        $this->assertEquals($obj->getName(), FileOperations::getChannelWikiName());
        $this->assertEquals($obj->getExtension(), "");
        $this->assertEquals($obj->getType(), "folder");

        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getDeletedItemsName(), $this->user->getId());
        $this->assertInstanceOf(Folder::class, $obj);
        $this->assertEquals($obj->getName(), FileOperations::getDeletedItemsName());
        $this->assertEquals($obj->getExtension(), "");
        $this->assertEquals($obj->getType(), "folder");

        // ... Verify Wiki Main Path exists
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index.md", $this->user->getId());
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);

        // Copy,  List, Upload
        $obj = $fo->copy("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index.md",
            "/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index1.md"
            , $this->user->getId());
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index1.md", $this->user->getId());
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index1.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);

        // ... Move
        $obj = $fo->move("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index1.md",
            "/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index2.md"
            , $this->user->getId());
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index2.md", $this->user->getId());
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index2.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);

        // ... Verify source is moved
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index1.md", $this->user->getId());
        $this->assertFalse($obj);

        // ... Delete
        $this->assertTrue($fo->delete("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index2.md", $this->user->getId()));
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index2.md", $this->user->getId());
        $this->assertFalse($obj);

        // ... Download
        $resource = "";
        $type = "";
        $this->assertTrue($fo->download("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelWikiName()."/index.md", "", $this->user->getId(), $resource, $type));
        $this->assertEquals($type, "redirect");
        $output = file_get_contents($resource);
        $expected = file_get_contents($this->getUploadFile());
        $this->assertEquals($output, $expected);

        // ... Upload
        $this->assertTrue($fo->upload("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName(), "indexup.md", $this->getUploadFile(), $this->user->getId()));
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName()."/indexup.md", $this->user->getId());
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "indexup.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);

        // ... List
        $items = $fo->list("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName(), 0, -1, $this->user->getId());
        $this->assertEquals(count($items["items"]), 2);

        $obj = $items["items"][0];
        $this->assertInstanceOf(Folder::class, $obj);
        $this->assertEquals($obj->getName(), FileOperations::getChannelAttachmentsName());
        $this->assertEquals($obj->getExtension(), "");
        $this->assertEquals($obj->getType(), "folder");

        $obj = $items["items"][1];
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "indexup.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);


        $items = $fo->list("/f/".$teamid."/Channels/".$channelName, 0, -1, $this->user->getId());
        $this->assertEquals(count($items["items"]), 3); // files, deleted, wiki

        // ... Versions
        // ... Upload one more version in the same path
        $this->assertTrue($fo->upload("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName(), "indexup.md", $this->getUploadFile(), $this->user->getId()));
        $items = $fo->versions("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName()."/indexup.md", $this->user->getId());
        $this->assertEquals(1, count($items));
        $obj = $items[0];
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "indexup.md");
        $this->assertEquals($obj->getFullPath(), "/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName()."/indexup.md");
        $this->assertEquals($obj->getParent(), "/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName());
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);
    }

    public function testFileOperationsDelete()
    {
        $dc = new DataController($this->container);

        $defaultTeamForUser = $dc->getDefaultTeamForUser($this->user->getId());
        $this->assertNotNull($defaultTeamForUser);
        $teamid = $defaultTeamForUser->getId();

        // .. Verify On New Channel Creation
        $co = $this->container->get(ChannelOperations::class);
        $channelName = "devfs" . time();
        $channel = $co->createChannel($this->user, $channelName);

        // ... Verify if Files and Wiki Folders are created
        $fo = $this->container->get(FileOperations::class);
        $obj = $fo->info("/f/" . $teamid . "/Channels/" . $channelName . "/".FileOperations::getChannelFilesName(), $this->user->getId());
        $this->assertEquals($obj->getName(), "files");

        $obj = $fo->info("/f/" . $teamid . "/Channels/" . $channelName . "/".FileOperations::getChannelWikiName(), $this->user->getId());
        $this->assertEquals($obj->getName(), "wiki");

        $obj = $fo->info("/f/" . $teamid . "/Channels/" . $channelName . "/".FileOperations::getDeletedItemsName(), $this->user->getId());
        $this->assertEquals($obj->getName(), FileOperations::getDeletedItemsName());

        ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // ... Add a file
        $this->assertTrue($fo->upload("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName(), "indexup.md", $this->getUploadFile(), $this->user->getId()));
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName()."/indexup.md", $this->user->getId());
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "indexup.md");

        // ... Delete a file via f
        $this->assertTrue($fo->delete("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName()."/indexup.md", $this->user->getId()));
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getChannelFilesName()."/indexup.md", $this->user->getId());
        $this->assertFalse($obj);

        // ... Moved Directly into team Recycle bin
        $obj = $fo->info("/f/".$teamid."/".FileOperations::getDeletedItemsName()."/Channels/".$channelName."/".FileOperations::getChannelFilesName()."/indexup.md", $this->user->getId());
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "indexup.md");

        // ... Really wiped
        $this->assertTrue($fo->delete("/f/".$teamid."/".FileOperations::getDeletedItemsName()."/Channels/".$channelName."/".FileOperations::getChannelFilesName()."/indexup.md", $this->user->getId()));
        $obj = $fo->info("/f/".$teamid."/".FileOperations::getDeletedItemsName()."/Channels/".$channelName."/".FileOperations::getChannelFilesName()."/indexup.md", $this->user->getId());
        $this->assertFalse($obj);
        ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
        // ... Delete file via cf
        $channelPaths = $dc->getChannelPathsByChannelId((int)$channel->getId());
        $channelFilesPath = "";
        $channelDeletedPath = "";
        foreach($channelPaths as $channelPathData) {
            $channelPath = ChannelPath::withDBData($channelPathData);
            // ... Convert the file path to channel path
            if ($channelPath->getPathType() == ChannelPath::CHANNEL_PATH_TYPE_FILE)
            {
                $channelFilesPath = "/cf/".$channelPath->getId();
            }
        }
        $this->assertTrue(strlen($channelFilesPath) > 0);

        $this->assertTrue($fo->upload($channelFilesPath, "indexup.md", $this->getUploadFile(), $this->user->getId()));
        $obj = $fo->info($channelFilesPath."/indexup.md", $this->user->getId());
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "indexup.md");

        // ... Delete a file via cf
        $this->assertTrue($fo->delete($channelFilesPath."/indexup.md", $this->user->getId()));
        $obj = $fo->info($channelFilesPath."/indexup.md", $this->user->getId());
        $this->assertFalse($obj);

        // ... Moved Directly into channel Recycle bin
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getDeletedItemsName()."/indexup.md", $this->user->getId());
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "indexup.md");

        // ... Delete a file via f
        $this->assertTrue($fo->delete("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getDeletedItemsName()."/indexup.md", $this->user->getId()));
        $obj = $fo->info("/f/".$teamid."/Channels/".$channelName."/".FileOperations::getDeletedItemsName()."/indexup.md", $this->user->getId());
        $this->assertFalse($obj);

        // ... Moved Directly into team Recycle bin
        $obj = $fo->info("/f/".$teamid."/".FileOperations::getDeletedItemsName()."/Channels/".$channelName."/".FileOperations::getDeletedItemsName()."/indexup.md", $this->user->getId());
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "indexup.md");

        // ... Really wiped
        $this->assertTrue($fo->delete("/f/".$teamid."/".FileOperations::getDeletedItemsName()."/Channels/".$channelName."/".FileOperations::getDeletedItemsName()."/indexup.md", $this->user->getId()));
        $obj = $fo->info("/f/".$teamid."/".FileOperations::getDeletedItemsName()."/Channels/".$channelName."/".FileOperations::getDeletedItemsName()."/indexup.md", $this->user->getId());
        $this->assertFalse($obj);

    }
}