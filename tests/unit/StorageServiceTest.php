<?php

use Codeception\Test\Unit;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Service\Storage\Config\StorageServiceConfig;
use CodeLathe\Service\Storage\Shared\RequestObject;
use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\Shared\StorageObject;
use CodeLathe\Service\Storage\StorageService;
use CodeLathe\Service\Storage\Util\StorageServiceUtility;
use CodeLathe\Service\Storage\Shared\FolderPropertiesObject;


if(!defined('CL_AS_ROOT_DIR')){
    define('CL_AS_ROOT_DIR', realpath(__DIR__ . '/../..'));
}

class StorageServiceTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $container;

    private static $sidecarMetadata1 = "THUMB|800X600";
    private static $sidecarMetadata2 = "THUMB|400X300";
    private static $importedFiles = 0;
    private static $importedFolders = 0;
    private static $versionIdentifier = NULL;
    private static $singleFileSize = 0;
    /**
     * @throws Exception
     */
    protected function _before()
    {

        $configRegistry = new ConfigRegistry();
        $containerIniter = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Init.php';
        $this->container = $containerIniter($configRegistry);
    }

    protected function _after()
    {
    }

    private function getTestFolderObject()
    {
        return array(
            StorageObject::OBJECT_PARENTPATH => "/",
            StorageObject::OBJECT_NAME => "test",
            StorageObject::OBJECT_TYPE => "folder",
            StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
            StorageObject::OBJECT_OWNER => "user1",
        );
    }

    private function getTestFileObject()
    {

        return array(
            StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_SITEID => "testsite",
            StorageObject::OBJECT_TYPE => "file",
            StorageObject::OBJECT_OWNER => "user1",
            StorageObject::OBJECT_CHUNKSTART => 0,
            StorageObject::OBJECT_COMPLETE => 1,

        );
    }

    private function uploadFile($singlefile, $uploadname, $chunksize)
    {
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $fileexists = file_exists($singlefile);
        if(!$fileexists){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to access file ".$singlefile);
            return FALSE;
        }

        //common file properties
        $file1 = $this->getTestFileObject();
        $file1[StorageObject::OBJECT_NAME] = $uploadname;

        $start = 0; $end = 0; $complete = 0;
        $buffer = '';
        $handle = fopen($singlefile, 'rb');
        if ($handle === false) {
            return false;
        }
        while (!feof($handle)) {
            $tempfolder = StorageServiceUtility::getTempFolder();
            $tempfile = \tempnam($tempfolder, "chunk");
            $buffer = fread($handle, $chunksize);
            $length = strlen($buffer);
            $end = $start + $length;
            if ($length < $chunksize) {
                $complete = 1;
            }
            $tmpfilehandle = \fopen($tempfile, 'w+');
            \fwrite($tmpfilehandle, $buffer);
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                .": Adding file (start, end, complete) :($start, $end, $complete)");
            //make the request
            $file1[StorageObject::OBJECT_CHUNKSTART] = $start;
            $file1[StorageObject::OBJECT_COMPLETE] = $complete;
            $file1[StorageObject::OBJECT_SRCPATH] = $tempfile;
            $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $file1);
            $resp = $service->service($req);
            unlink($tempfile);
            $this->assertNotNull($resp);
            $this->assertTrue($resp->isSuccess());
            $start = $end;
        }
        return TRUE;
    }


    public function testSingleFileFolderAdd()
    {
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $folderObject = $this->getTestFolderObject();
        $fileObject = $this->getTestFileObject();
        $singlefile = CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'wiki'.DIRECTORY_SEPARATOR.'index.md';
        self::$singleFileSize = filesize($singlefile);
        $this->assertFileExists($singlefile);
        $fileObject[StorageObject::OBJECT_SRCPATH] = $singlefile;

        //Add folder
        $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $folderObject);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());

        //Add file
        $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $fileObject);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());
    }

    public function testDownloadFileDirect(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
            StorageObject::OBJECT_DL_RESPONSETYPE => ResponseObject::RESPONSETYPE_DOWNLOADREDIRECT
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $file1);
        $resp = $service->service($req);
        $downloadInfo = $resp->getDownloadInfo();
        $this->assertNotEmpty($downloadInfo);
    }

    public function testDownloadFileStoreAndForward(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
            StorageObject::OBJECT_DL_RESPONSETYPE => ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $file1);
        $resp = $service->service($req);
        $downloadInfo = $resp->getDownloadInfo();
        $this->assertNotEmpty($downloadInfo);
        codecept_debug($downloadInfo);
        $this->assertIsArray($downloadInfo);
        $this->assertNotNull($downloadInfo[0]);
        $this->assertNotNull($downloadInfo[0]['tmpfile']);
        $tmpfile = $downloadInfo[0]['tmpfile'];
        unlink($tmpfile);
    }

    public function testGetFileInfo(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECTINFO, $file1);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        $storageObject = $resp->getStorageObject();
        $this->assertTrue(empty($storageObject->getObjectVersionIdentifier()));
        $this->assertEquals($storageObject->getFileSize(), self::$singleFileSize);
    }

    public function testFileVersionsAdd()
    {
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $fileObject = $this->getTestFileObject();
        $singlefile = CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'wiki'.DIRECTORY_SEPARATOR.'index.md';
        $this->assertFileExists($singlefile);
        $fileObject[StorageObject::OBJECT_SRCPATH] = $singlefile;

        //Add file version 1
        $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $fileObject);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());

        //Add file version 2
        $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $fileObject);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());
    }

    public function testListFileVersions()
    {
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $fileObject = $this->getTestFileObject();

        //List file versions
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETFILEVERSIONLIST, $fileObject);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());

        $versionobjects = $resp->getStorageObjects();
        $this->assertTrue(count($versionobjects) == 2);
        /** @var StorageObject $versionobject */
        $versionobject = $versionobjects[0];
        $this->assertNotNull($versionobject->getObjectVersionIdentifier());
        self::$versionIdentifier = $versionobject->getObjectVersionIdentifier();
    }

    public function testGetFileVersion(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $file1 = $this->getTestFileObject();
        $file1[StorageObject::OBJECT_VERSIONIDENTFIER] = self::$versionIdentifier;

        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $file1);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        $downloadInfo = $resp->getDownloadInfo();
        codecept_debug("Location ".$downloadInfo);
        $this->assertNotEmpty($downloadInfo);
    }

    public function testListFiles(): void
    {
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $folderObject = $this->getTestFolderObject();
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETFILELIST, $folderObject);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());
        $this->assertEquals($resp->getTotalRowCount(), 1);
    }

    public function testAddMetaTags(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $metatags = array("tag1" => "value1", "tag2" => "value2");
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
            StorageObject::OBJECT_METATAGS => json_encode($metatags)

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_SETOBJECTMETATAGS, $file1);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());
    }

    public function testGetMetaTags(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECTMETATAGS, $file1);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        $storageobject = $resp->getStorageObject();
        $this->assertNotNull($storageobject);
        $jsonobject = json_decode($storageobject->getMetaTagsAsString());
        codecept_debug($jsonobject);
        $this->assertNotNull($jsonobject);
        $this->assertNotNull($jsonobject->tag1);
        $this->assertEquals($jsonobject->tag1, "value1");

    }

    public function testUpdateMetaTags(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $metatags = array("tag3" => "value1", "tag4" => "value2");
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
            StorageObject::OBJECT_METATAGS => json_encode($metatags)

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_UPDATEOBJECTMETATAGS, $file1);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());
    }

    public function testGetUpdatedMetaTags(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECTMETATAGS, $file1);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        $storageobject = $resp->getStorageObject();
        $this->assertNotNull($storageobject);
        $jsonobject = json_decode($storageobject->getMetaTagsAsString());
        codecept_debug($jsonobject);
        $this->assertNotNull($jsonobject);
        $this->assertTrue(!isset($jsonobject->tag1));
        $this->assertNotNull($jsonobject->tag3);
        $this->assertEquals($jsonobject->tag3, "value1");
    }

    public function testRemoveMetaTags(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_REMOVEOBJECTMETATAGS, $file1);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());
    }

    public function testGetDeletedMetaTags(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECTMETATAGS, $file1);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertFalse($resp->isSuccess());
    }

    public function testAddSidecarFile(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $singlefile = CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'wiki'.DIRECTORY_SEPARATOR.'index.md';
        $this->assertFileExists($singlefile);
        $fileObject = $this->getTestFileObject();
        $fileObject[StorageObject::OBJECT_SRCPATH] = $singlefile;
        $fileObject[StorageObject::OBJECT_TYPE] = StorageObject::OBJECTTYPE_SIDECARFILE;
        $fileObject[StorageObject::OBJECT_SIDECARMETADATA] = self::$sidecarMetadata1;

        //add sidecar 1
        $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $fileObject);
        $service->service($req);

        //add sidecar 2
        $fileObject[StorageObject::OBJECT_SIDECARMETADATA] = self::$sidecarMetadata2;
        $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $fileObject);
        $service->service($req);
    }

    public function testListSidecarFiles(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $fileObject = $this->getTestFileObject();
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETSIDECARLIST, $fileObject);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());

        $sidecarobjects = $resp->getStorageObjects();
        $this->assertTrue(count($sidecarobjects) == 2);
        /** @var StorageObject $sidecarobject */
        $sidecarobject = $sidecarobjects[0];
        $this->assertNotNull($sidecarobject->getSidecarMetadataAsString());
        $this->assertTrue(($sidecarobject->getSidecarMetadataAsString() === self::$sidecarMetadata1
            || $sidecarobject->getSidecarMetadataAsString() === self::$sidecarMetadata2));
    }

    public function testGetSidecarInfo(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $fileObject = $this->getTestFileObject();
        $fileObject[StorageObject::OBJECT_TYPE] = StorageObject::OBJECTTYPE_SIDECARFILE;
        $fileObject[StorageObject::OBJECT_SIDECARMETADATA] = self::$sidecarMetadata1;

        $req = RequestObject::createRequest(RequestObject::REQUEST_GETSIDECARINFO, $fileObject);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());

        /** @var StorageObject $sidecarobject */
        $storageObject = $resp->getStorageObject();
        $this->assertNotNull($storageObject);
        $this->assertEquals($storageObject->getFileSize(), self::$singleFileSize);
    }

    public function testGetSidecar(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $fileObject = $this->getTestFileObject();
        $fileObject[StorageObject::OBJECT_TYPE] = StorageObject::OBJECTTYPE_SIDECARFILE;
        $fileObject[StorageObject::OBJECT_SIDECARMETADATA] = self::$sidecarMetadata1;

        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $fileObject);
        $resp = $service->service($req);
        $downloadInfo = $resp->getDownloadInfo();
        codecept_debug($downloadInfo);
        $this->assertNotEmpty($downloadInfo);
    }

    public function testDeleteSidecarFiles(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $fileObject = $this->getTestFileObject();

        //delete all sidecars
        $req = RequestObject::createRequest(RequestObject::REQUEST_DELETEOBJECTSIDECARS, $fileObject);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());

        //ensure all are deleted
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETSIDECARLIST, $fileObject);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());
        $sidecarobjects = $resp->getStorageObjects();
        $this->assertTrue(count($sidecarobjects) == 0);
    }


    public function testSingleFileDelete()
    {
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $file1 = $this->getTestFileObject();

        //Delete file
        $req = RequestObject::createRequest(RequestObject::REQUEST_DELETEOBJECT, $file1);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());

        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECTINFO, $file1);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue(!$resp->isSuccess());
    }

    public function testImportFromDisk(){

        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $destpath = "/test";
        $testfolder = CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'wiki';
        $this->assertDirectoryExists($testfolder);

        $objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($testfolder),
            \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($objects as $name => $object) {

            if ($object->getFileName() == "." || $object->getFileName() == "..") {
                continue;
            }

            // Ready to import this file
            $importName = $destpath.substr($object->getPathName(), strlen($testfolder));
            $path_parts = StorageServiceUtility::mb_pathinfo($importName);
            $parentPath = str_replace("\\", "/", $path_parts['dirname']);
            $name = $object->getFileName();
            if (empty($name)) {
                $name = $path_parts['basename'];
                if (isset($path_parts['extension'])) {
                    $name .= "." . $path_parts['extension'];
                }
            }

            //import files and folders by calling the seeddatastore class
            //seeddatastore caches these imports and doesn't commit to db until cache is full
            if (is_dir($object->getPathName())) {
                $storageitem = $this->getTestFolderObject();
                $storageitem[StorageObject::OBJECT_PARENTPATH] = $parentPath;
                $storageitem[StorageObject::OBJECT_NAME] = $name;
                self::$importedFolders++;
            } else {
                $storageitem = $this->getTestFileObject();
                $storageitem[StorageObject::OBJECT_PARENTPATH] = $parentPath;
                $storageitem[StorageObject::OBJECT_NAME] = $name;
                $storageitem[StorageObject::OBJECT_SRCPATH] = $object->getPathName();
                self::$importedFiles++;
            }
            $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $storageitem);
            $service->service($req);
        }
        codecept_debug("Imported files/folders: ".self::$importedFiles."/".self::$importedFolders);
    }

    public function testGetFolderProperties(){
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $folder = $this->getTestFolderObject();
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETFOLDERPROPERTIES, $folder);
        /** @var ResponseObject $resp */
        $resp = $service->service($req);
        /** @var FolderPropertiesObject $properties */
        $properties = $resp->getFolderPropertiesObject();
        codecept_debug("Storage files/folders: ".$properties->getTotalFileCount()."/".$properties->getTotalFolderCount());
        $this->assertEquals($properties->getTotalFolderCount(), self::$importedFolders);
        $this->assertEquals($properties->getTotalFileCount(), self::$importedFiles);
    }

    public function testDirectChunkedUpload()
    {
        $singlefile = CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'wiki'.DIRECTORY_SEPARATOR.'index.md';
        $this->assertFileExists($singlefile);
        $uploadname = "chunkeddirect.dat";
        $chunksize = 512;
        $this->uploadFile($singlefile, $uploadname, $chunksize);
    }

    public function testRecursiveFolderDelete()
    {
        /** @var StorageService $service */
        $service = $this->container->get(StorageService::class);
        $folderObject = $this->getTestFolderObject();

        //Delete file
        $req = RequestObject::createRequest(RequestObject::REQUEST_DELETEOBJECT, $folderObject);
        $resp = $service->service($req);
        $this->assertNotNull($resp);
        $this->assertTrue($resp->isSuccess());
    }
}