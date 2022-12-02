<?php

use Codeception\Test\Unit;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Files\FileController;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Objects\File;
use CodeLathe\Core\Objects\Folder;
use CodeLathe\Core\Objects\User;
use Psr\Log\LoggerInterface;

defined('CL_AS_ROOT_DIR') or define('CL_AS_ROOT_DIR', realpath(__DIR__ . '/../..'));

class FileControllerTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $container;


    /**
     * @throws Exception
     */
    protected function _before()
    {

        $configRegistry = new ConfigRegistry();
        $containerIniter = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Init.php';
        $this->container = $containerIniter($configRegistry);
        \CodeLathe\Core\Utility\ContainerFacade::setUp($this->container);
    }

    protected function _after()
    {
    }

    private function getUploadFile()
    {
        return CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'wiki'.DIRECTORY_SEPARATOR.'index.md';
    }

    public function testDownload()
    {
        $fc = $this->container->get(FileController::class);

        if ($fc->info("/f/42") !== false)
            $this->assertTrue($fc->delete("/f/42" ));

        $this->assertTrue($fc->create("/f/", "42",0 ));

        $this->assertTrue($fc->upload("/f/42", "index.md", $this->getUploadFile()));
        $this->assertIsObject($fc->info("/f/42/index.md"));

        $resource = "";
        $type = "";
        $this->assertTrue($fc->download("/f/42/index.md", "", $resource, $type));
        $this->assertEquals($type, "redirect");
        $output = file_get_contents($resource);
        $expected = file_get_contents($this->getUploadFile());
        $this->assertEquals($output, $expected);
   }

    public function testList()
    {
        $fc = $this->container->get(FileController::class);

        if ($fc->info("/f/42") !== false)
            $this->assertTrue($fc->delete("/f/42" ));

        $this->assertTrue($fc->create("/f/", "42",0 ));
        $items = $fc->list("/f/42", 0, -1);
        $this->assertTrue(count($items["items"]) == 0);

        $this->assertTrue($fc->upload("/f/42", "index.md", $this->getUploadFile()));
        $this->assertInstanceOf(File::class, $fc->info("/f/42/index.md"));

        $items = $fc->list("/f/42", 0, -1, "/f/42");
        $this->assertTrue(count($items["items"]) == 1);
        $this->assertTrue($items["total"] == 1);
        $obj = $items["items"][0];
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index.md");
        $this->assertEquals($obj->getFullPath(), "/f/42/index.md");
        $this->assertEquals($obj->getParent(), "/f/42");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);

        $this->assertTrue($fc->create("/f/42", "child", 0));
        $this->assertInstanceOf(Folder::class, $fc->info("/f/42/child"));

        // ... No Limits
        $items = $fc->list("/f/42", 0, -1);
        $this->assertTrue(count($items["items"]) == 2);

        // ... Try with limits
        $items = $fc->list("/f/42", 0, 2);
        $this->assertTrue(count($items["items"]) == 2);

        // ... Try with limits
        $items = $fc->list("/f/42", 0, 1);
        $this->assertTrue(count($items["items"]) == 1);

        // ... Try with limits
        $items = $fc->list("/f/42", 0, 0);
        $this->assertTrue(count($items["items"]) == 0);

        $this->assertTrue($fc->delete("/f/42/child" ));
        $items = $fc->list("/f/42", 0, -1);
        $this->assertTrue(count($items["items"]) == 1);

        $this->assertTrue($fc->delete("/f/42/index.md" ));
        $items = $fc->list("/f/42", 0, -1);
        $this->assertTrue(count($items["items"]) == 0);

        // ... Delete the path and let's try the list
        $this->assertTrue($fc->delete("/f/42" ));

        $items = $fc->list("/f/42", 0, -1);
        $this->assertTrue($items === false);

    }

    public function testUpload()
    {
        $fc = $this->container->get(FileController::class);

        if ($fc->info("/f/42") !== false)
            $this->assertTrue($fc->delete("/f/42" ));

        $this->assertTrue($fc->create("/f/", "42",0 ));

        $this->assertTrue($fc->upload("/f/42", "index.md", $this->getUploadFile()));
        $this->assertIsObject($fc->info("/f/42/index.md"));

        $obj = $fc->info("/f/42/index.md");
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);
    }

    public function testVersions()
    {
        $fc = $this->container->get(FileController::class);

        if ($fc->info("/f/42") !== false)
            $this->assertTrue($fc->delete("/f/42" ));

        $this->assertTrue($fc->create("/f/", "42", 0));

        // ... Upload 3 versions
        $numVersions = 3;
        for ($i = 0; $i < $numVersions; $i++) {
            $this->assertTrue($fc->upload("/f/42", "index.md", $this->getUploadFile()));
            $this->assertIsObject($fc->info("/f/42/index.md"));
        }

        $items = $fc->versions("/f/42/index.md", "/f/42");
        $this->assertEquals(count($items) + 1, $numVersions);


        for ($i = 0; $i < ($numVersions - 1); $i++) {
            $obj = $items[$i];
            $this->assertInstanceOf(File::class, $obj);
            $this->assertEquals($obj->getName(), "index.md");
            $this->assertEquals($obj->getFullPath(), "/f/42/index.md");
            $this->assertEquals($obj->getParent(), "/f/42");
            $this->assertEquals($obj->getExtension(), "md");
            $this->assertEquals($obj->getType(), "file");
            $this->assertTrue($obj->getSize() > 0);
        }
    }

    public function testCopyFile()
    {
        $fc = $this->container->get(FileController::class);

        if ($fc->info("/f/42") !== false)
            $this->assertTrue($fc->delete("/f/42" ));

        if ($fc->info("/f/42copy") !== false)
            $this->assertTrue($fc->delete("/f/42copy" ));


        $this->assertTrue($fc->create("/f/", "42", 0));

        $this->assertTrue($fc->upload("/f/42", "index.md", $this->getUploadFile()));
        $this->assertIsObject($fc->info("/f/42/index.md"));

        $obj = $fc->info("/f/42/index.md");
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);

        $this->assertTrue($fc->copy("/f/42", "/f/42copy"));
        $items = $fc->list("/f/42copy", 0, -1);
        $this->assertTrue(count($items["items"]) == 1);

        $obj = $fc->info("/f/42copy/index.md");
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);
    }

    public function testCopyFolder()
    {
        $fc = $this->container->get(FileController::class);

        if ($fc->info("/f/42") !== false)
            $this->assertTrue($fc->delete("/f/42" ));

        if ($fc->info("/f/42copy") !== false)
            $this->assertTrue($fc->delete("/f/42copy" ));


        $this->assertTrue($fc->create("/f/", "42",0 ));

        $this->assertTrue($fc->create("/f/42", "child",0));

        $this->assertTrue($fc->upload("/f/42/child", "index.md", $this->getUploadFile()));
        $this->assertIsObject($fc->info("/f/42/child/index.md"));

        $obj = $fc->info("/f/42/child/index.md");
        $this->assertInstanceOf(File::class, $obj);

        $this->assertTrue($fc->copy("/f/42", "/f/42copy"));
        $items = $fc->list("/f/42copy", 0, -1);
        $this->assertTrue(count($items["items"]) == 1);
        $obj = $fc->info("/f/42copy/child");
        $this->assertInstanceOf(Folder::class, $obj);

        $obj = $fc->info("/f/42copy/child/index.md");
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);
    }

    public function testMoveFile()
    {
        $fc = $this->container->get(FileController::class);

        if ($fc->info("/f/42") !== false)
            $this->assertTrue($fc->delete("/f/42" ));

        if ($fc->info("/f/42copy") !== false)
            $this->assertTrue($fc->delete("/f/42copy" ));


        $this->assertTrue($fc->create("/f/", "42", 0));

        $this->assertTrue($fc->upload("/f/42", "index.md", $this->getUploadFile()));
        $this->assertIsObject($fc->info("/f/42/index.md"));

        $obj = $fc->info("/f/42/index.md");
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);

        $this->assertTrue($fc->move("/f/42", "/f/42copy"));
        $items = $fc->list("/f/42copy", 0, -1);
        $this->assertTrue(count($items["items"]) == 1);

        $obj = $fc->info("/f/42copy/index.md");
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);

        $this->assertTrue($fc->info("/f/42") === false);
        $this->assertTrue($fc->info("/f/42/index.md") === false);
    }

    public function testMoveFolder()
    {
        $fc = $this->container->get(FileController::class);

        if ($fc->info("/f/42") !== false)
            $this->assertTrue($fc->delete("/f/42" ));

        if ($fc->info("/f/42copy") !== false)
            $this->assertTrue($fc->delete("/f/42copy" ));


        $this->assertTrue($fc->create("/f/", "42",0));

        $this->assertTrue($fc->create("/f/42", "child",0));

        $this->assertTrue($fc->upload("/f/42/child", "index.md", $this->getUploadFile()));
        $this->assertIsObject($fc->info("/f/42/child/index.md"));

        $obj = $fc->info("/f/42/child/index.md");
        $this->assertInstanceOf(File::class, $obj);

        $this->assertTrue($fc->move("/f/42", "/f/42copy"));
        $items = $fc->list("/f/42copy", 0, -1);
        $this->assertTrue(count($items["items"]) == 1);
        $obj = $fc->info("/f/42copy/child");
        $this->assertInstanceOf(Folder::class, $obj);

        $obj = $fc->info("/f/42copy/child/index.md");
        $this->assertInstanceOf(File::class, $obj);
        $this->assertEquals($obj->getName(), "index.md");
        $this->assertEquals($obj->getExtension(), "md");
        $this->assertEquals($obj->getType(), "file");
        $this->assertTrue($obj->getSize() > 0);


        $this->assertTrue($fc->info("/f/42") === false);
        $this->assertTrue($fc->info("/f/42/child") === false);
        $this->assertTrue($fc->info("/f/42/child/index.md") === false);
    }


    public function testCreatePaths()
    {
        $fc = $this->container->get(FileController::class);

        if ($fc->info("/f/42") !== false)
            $this->assertTrue($fc->delete("/f/42" ));

        $this->assertTrue($fc->create("/f/", "42",0 ));
        $this->assertIsObject($fc->info("/f/42"));

        $this->assertTrue($fc->create("/f/42", "dev.as",0 ));
        $this->assertIsObject($fc->info("/f/42/dev.as"));

        $obj = $fc->info("/f/42/dev.as");
        $this->assertInstanceOf(Folder::class, $obj);
        $this->assertEquals($obj->getName(), "dev.as");
        $this->assertEquals($obj->getExtension(), "");
        $this->assertEquals($obj->getType(), "folder");

        $this->assertTrue($fc->create("/f/42/dev.as", "fromunit",0));
        $this->assertIsObject($fc->info("/f/42/dev.as/fromunit"));

        // ... Call the same path again and it should succeed if path exists
        $this->assertTrue($fc->create("/f/42/dev.as", "fromunit", 0));

        // ... Call a non existing base path and it should fail
        $this->assertFalse($fc->create("/f/42/dev.as/random/".time(), "fromunit", 0));

        $this->assertTrue($fc->createAllPaths("/f/42/dev.as/sub1/sub2/sub3/sub4", 0));
        $this->assertIsObject($fc->info("/f/42/dev.as/sub1/sub2/sub3/sub4"));

        // .. Fully Done, clean up
        $this->assertTrue($fc->delete("/f/42" ));
        $this->assertFalse($fc->info("/f/42"));
    }

}