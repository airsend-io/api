<?php declare(strict_types=1);

/******************************************************************************* 
  Copyright(c) 2012 CodeLathe LLC. All rights Reserved.
  This file is part of TonidoCloud  http://www.tonido.com
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Unittest;

use Aws\StorageGateway\Exception\StorageGatewayException;
use CodeLathe\Service\Storage\Config\StorageServiceConfig;
use CodeLathe\Service\Storage\Shared\RequestObject;
use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\Shared\StorageObject;
use CodeLathe\Service\Storage\StorageService;
use CodeLathe\Service\Storage\Util\StorageServiceUtility;


/**
 * Class to do a quicktest of storage APIs.
 * This class will be replaced with PHPUnit tests
 *
 * Class QuickTest
 * @package CodeLathe\Service\Storage\Unittest
 */
class QuickTest{

    /** @var StorageService */
    private $service;

    /**
     * QuickTest constructor.
     */
    public function __construct(StorageService $service)
    {
        $this->service = $service;

    }

    /**
     * Entry point for quick tests
     * Uncomment tests to run
     */
    public function quickTest(): void
    {
        $delete = TRUE;
        //$this->testSingleFolder();                //adds folder /test
        //$this->testSingleFolder($delete);         //deletes folder /test
        //$this->testSingleFile();                  //adds a single file /test/firstfile.dat
        //$this->testSingleFile($delete);           //deletes single file /test/firstfile.dat
        //$this->testImportFromDisk();              //imports file/folders under resources dir into /test
        //$this->testCopyFile();                    //copies file /test/firstfile.dat to /test/firstfile_copy.dat
        //$this->testCopyFile($delete);             //deletes file /test/firstfile_copy.dat
        //$this->testMoveFile();                    //copies file /test/firstfile.dat to /test/firstfile_move.dat
        //$this->testMoveFile($delete);             //deletes file /test/firstfile_move.dat
        //$this->testCopyFolder();                  //copies folder /test to /test_copied
        //$this->testCopyFolder($delete);           //deletes folder /test_copied
        //$this->testMoveFolder();                  //moves folder /test to /test_moved
        //$this->testMoveFolder($delete);           //deletes folder /test_moved
        //$this->testListFiles();                   //list folder /test contents
        //$this->testGetFileInfo();                 //get file/folder info
        //$this->testListFileVersions();            //list versions of a file
        //$this->testGetFileVersion();              //download particular version of a file
        //$this->testGetFile();                     //download file
        //$this->testDirectChunkedUpload();         //chunked upload (direct, no multipart)
        //$this->testMultipartChunkedUpload();      //chunked upload (direct, multipart)
        //$this->testAddMetaTags();                 //Add metatags
        //$this->testGetMetaTags();                 //Get metatags
        //$this->testUpdateMetaTags();              //Get metatags
        //$this->testRemoveMetaTags();              //Get metatags
        //$this->testAddSidecarFile();              //adds a sidecar file /test/firstfile.dat
        //$this->testListSidecarFiles();            //list all sidecar file /test/firstfile.dat
        //$this->testGetSidecarInfo();              //get info of a single sidecar of live file /test/firstfile.dat
        //$this->testGetSidecar();                  //download a sidecar file
        //$this->testDeleteSidecarFiles();          //delete all sidecars of a file
        //$this->testGetFolderProperties();           //get folder properties
    }

    public function service(RequestObject $requestObject): ResponseObject
    {
        return $this->service->service($requestObject);
    }

    /**
     * @param bool $delete
     */
    public function testSingleFolder($delete = FALSE) : void
    {
        $folder1 = array(
            StorageObject::OBJECT_PARENTPATH => "/",
            StorageObject::OBJECT_NAME => "test",
            StorageObject::OBJECT_TYPE => "folder",
            StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
            StorageObject::OBJECT_OWNER => "user1",
        );
        $req1 = RequestObject::createRequest(RequestObject::REQUEST_DELETEOBJECT, $folder1);
        $req2 = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $folder1);
        if($delete) $this->service($req1);
        else $this->service($req2);

    }

    public function testSingleFile($delete = FALSE){
        $singlefile = realpath(__DIR__."/../../../../"."resources".DIRECTORY_SEPARATOR."README.md");
        $fileexists = file_exists($singlefile);
        if(!$fileexists){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to access file ".$singlefile);
            return;
        }
        $file1 = array(
            StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_SITEID => "testsite",
            StorageObject::OBJECT_TYPE => "file",
            StorageObject::OBJECT_OWNER => "user1",
            StorageObject::OBJECT_SRCPATH => $singlefile,
            StorageObject::OBJECT_CHUNKSTART => 0,
            StorageObject::OBJECT_COMPLETE => 1,

        );
        $req3 = RequestObject::createRequest(RequestObject::REQUEST_DELETEOBJECT, $file1);
        $req4 = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $file1);
        if($delete) $this->service($req3);
        else $this->service($req4);
    }

    public function testImportFromDisk(){
        $destpath = "/test";
        $testfolder = realpath(__DIR__."/../../../../"."resources");
        $fileexists = file_exists($testfolder);
        if(!$fileexists){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to access folder ".$testfolder);
            return;
        }

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
                StorageService::logger()->debug( __FUNCTION__.": Adding folder $parentPath/$name");
                $storageitem = array(
                    StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
                    StorageObject::OBJECT_PARENTPATH => $parentPath,
                    StorageObject::OBJECT_NAME => $name,
                    StorageObject::OBJECT_TYPE => "folder",
                    StorageObject::OBJECT_OWNER => "user1"

                );

            } else {
                StorageService::logger()->debug( "Adding File $parentPath/$name->".$object->getPathName());
                $storageitem = array(
                    StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
                    StorageObject::OBJECT_PARENTPATH => $parentPath,
                    StorageObject::OBJECT_NAME => $name,
                    StorageObject::OBJECT_TYPE => "file",
                    StorageObject::OBJECT_SITEID => "testsite",
                    StorageObject::OBJECT_OWNER => "user1",
                    StorageObject::OBJECT_SRCPATH =>  $object->getPathName(),
                    StorageObject::OBJECT_CHUNKSTART => 0,
                    StorageObject::OBJECT_COMPLETE => 1,

                );
            }
            $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $storageitem);
            $this->service->service($req);
        }
    }

    public function testCopyFile(): void
    {
        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
        );
        $tgtitem = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile_copy.dat",
            StorageObject::OBJECT_TYPE => "file"
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_COPYOBJECT, $srcitem);
        $req->setDstStorageObject($tgtitem);
        $this->service->service($req);
    }

    public function testMoveFile(): void
    {
        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
        );
        $tgtitem = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile_move.dat",
            StorageObject::OBJECT_TYPE => "file"
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_MOVEOBJECT, $srcitem);
        $req->setDstStorageObject($tgtitem);
        $this->service->service($req);
    }

    public function testCopyFolder($delete = FALSE): void
    {
        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => "/",
            StorageObject::OBJECT_NAME => "test"
        );
        $tgtitem = array(
            StorageObject::OBJECT_PARENTPATH => "/",
            StorageObject::OBJECT_NAME => "test_copied"
        );

        if($delete){
            $req = RequestObject::createRequest(RequestObject::REQUEST_DELETEOBJECT, $tgtitem);
            $this->service->service($req);
        } else{
            $req = RequestObject::createRequest(RequestObject::REQUEST_COPYOBJECT, $srcitem);
            $req->setDstStorageObject($tgtitem);
            $this->service->service($req);
        }

    }

    public function testMoveFolder($delete = FALSE): void
    {
        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => "/",
            StorageObject::OBJECT_NAME => "test"
        );
        $tgtitem = array(
            StorageObject::OBJECT_PARENTPATH => "/",
            StorageObject::OBJECT_NAME => "test_moved"
        );

        if($delete){
            $req = RequestObject::createRequest(RequestObject::REQUEST_DELETEOBJECT, $tgtitem);
            $this->service->service($req);
        } else{
            $req = RequestObject::createRequest(RequestObject::REQUEST_MOVEOBJECT, $srcitem);
            $req->setDstStorageObject($tgtitem);
            $this->service->service($req);
        }
    }

    public function testListFiles(): void
    {
        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => "/",
            StorageObject::OBJECT_NAME => "test"
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETFILELIST, $srcitem);
        $resp = $this->service->service($req);
        $count = $resp->getTotalRowCount();
    }

    public function testListFileVersions(){
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETFILEVERSIONLIST, $file1);
        $resp = $this->service->service($req);
        $i = 0;
    }

    public function testGetFileVersion(){
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
           // StorageObject::OBJECT_VERSIONIDENTFIER => "2019-09-26 21:42:16",
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $file1);
        $resp = $this->service->service($req);
    }

    public function testGetFileInfo(){
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECTINFO, $file1);
        $resp = $this->service->service($req);

    }

    public function testGetFile(){
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
            StorageObject::OBJECT_DL_RESPONSETYPE => ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $file1);
        $resp = $this->service->service($req);
    }

    private function uploadFile($singlefile, $uploadname, $chunksize)
    {
        $fileexists = file_exists($singlefile);
        if(!$fileexists){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to access file ".$singlefile);
            return FALSE;
        }

        //common file properties
        $file1 = array(
            StorageObject::OBJECT_STORAGEZONEID => "S3_US_EAST1",
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => $uploadname,
            StorageObject::OBJECT_TYPE => "file",
            StorageObject::OBJECT_OWNER => "user1",
        );

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
            $this->service($req);
            unlink($tempfile);
            $start = $end;
        }
        return TRUE;
    }

    public function testDirectChunkedUpload()
    {
        $singlefile = CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'composer.json';
        $uploadname = "chunkeddirect.dat";
        $chunksize = 512;
        $this->uploadFile($singlefile, $uploadname, $chunksize);
    }

    public function testMultipartChunkedUpload()
    {
        $singlefile = CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'scratch'.DIRECTORY_SEPARATOR .'largefile.dmg';
        $uploadname = "chunkedmulti.dat";
        $chunksize = 5 * 1024 * 1024;
        $this->uploadFile($singlefile, $uploadname, $chunksize);
    }

    public function testAddMetaTags(){
        $metatags = array("tag1" => "value1", "tag2" => "value2");
        $file1 = array(
            StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
            StorageObject::OBJECT_METATAGS => json_encode($metatags)

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_SETOBJECTMETATAGS, $file1);
        $resp = $this->service($req);
    }

    public function testGetMetaTags(){
        $file1 = array(
            StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECTMETATAGS, $file1);
        $resp = $this->service($req);
    }

    public function testUpdateMetaTags(){
        $metatags = array("tag3" => "value1", "tag4" => "value2");
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
            StorageObject::OBJECT_METATAGS => json_encode($metatags)

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_UPDATEOBJECTMETATAGS, $file1);
        $resp = $this->service($req);
    }

    public function testRemoveMetaTags(){
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => "file",
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_REMOVEOBJECTMETATAGS, $file1);
        $resp = $this->service($req);
    }

    public function testAddSidecarFile(){
        $singlefile = realpath(__DIR__."/../../../../"."resources".DIRECTORY_SEPARATOR."README.md");
        $fileexists = file_exists($singlefile);
        if(!$fileexists){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to access file ".$singlefile);
            return;
        }
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_SITEID => "testsite",
            StorageObject::OBJECT_TYPE => StorageObject::OBJECTTYPE_SIDECARFILE,
            StorageObject::OBJECT_OWNER => "user1",
            StorageObject::OBJECT_SRCPATH => $singlefile,
            StorageObject::OBJECT_SIDECARMETADATA => "THUMB|800X600"

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $file1);
        $this->service($req);
    }

    public function testListSidecarFiles(){
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETSIDECARLIST, $file1);
        $resp = $this->service($req);
    }

    public function testGetSidecarInfo(){
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => StorageObject::OBJECTTYPE_SIDECARFILE,
            StorageObject::OBJECT_SIDECARMETADATA => "THUMB|800X600"

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETSIDECARINFO, $file1);
        $resp = $this->service($req);
    }

    public function testGetSidecar(){
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
            StorageObject::OBJECT_TYPE => StorageObject::OBJECTTYPE_SIDECARFILE,
            StorageObject::OBJECT_SIDECARMETADATA => "THUMB|800X600"
            //StorageObject::OBJECT_DL_RESPONSETYPE => ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD //this is default

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $file1);
        $resp = $this->service($req);
    }

    public function testDeleteSidecarFiles(){
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/test",
            StorageObject::OBJECT_NAME => "firstfile.dat",
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_DELETEOBJECTSIDECARS, $file1);
        $resp = $this->service($req);
    }

    public function testGetFolderProperties(){
        $file1 = array(
            StorageObject::OBJECT_PARENTPATH => "/",
            StorageObject::OBJECT_NAME => "test",
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETFOLDERPROPERTIES, $file1);
        /** @var ResponseObject $resp */
        $resp = $this->service($req);
        $properties = $resp->getFolderPropertiesObject();

    }
}
?>