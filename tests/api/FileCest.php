<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Class FileCest
 * @group file
 */
class FileCest
{
    protected const DEFAULT_USER_AGENT = 'API Tests Suite';

    protected $jwtToken;
    protected $channelId;
    protected $testEmail;
    protected $password;
    protected $displayName;
    protected $channelName;

    public function _before(ApiTester $I)
    {
        $this->jwtToken = "";
        $this->testEmail = 'filetest@airsendmail.com';
        $this->password = 'password1';
        $this->displayName = 'File API Test User';
        $this->channelName = "New Test Channel";

        $I->haveHttpHeader('accept', 'application/json');
        $I->haveHttpHeader('content-type', 'application/json');

        // Create a test user
        $this->createUser($I, $this->testEmail, $this->password, $this->displayName);

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $userId = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, $this->testEmail, $this->displayName, 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->createChannel($I, $this->channelName);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;
    }

    protected function adminLogin(ApiTester $I, string $email, string $password)
    {
        $payload = [];
        $payload['email'] = $email;
        $payload['password'] = $password;


        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/admin.login', $payload);
        if ($token = $I->grabDataFromResponseByJsonPath('$.token')[0] ?? null) {
            $this->jwtToken = $token;
        }
    }

    protected function adminUserApprove(ApiTester $I, string $userId)
    {
        $payload = [];
        $payload['user_id'] = $userId;


        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/admin.user.approve', $payload);

    }

    protected function adminUserUpdate(ApiTester $I, string $userId, string $email, string $name, int $user_role, int $account_status, int $trust_level, int $is_locked,
                                       string $approval_status, string $is_email_verified, string $is_phone_verified, string $is_auto_pwd)
    {
        $payload = [];
        $payload['user_id'] = $userId;
        $payload['email'] = $email;
        $payload['name'] = $name;
        $payload['user_role'] = $user_role;
        $payload['account_status'] = $account_status;
        $payload['trust_level'] = $trust_level;
        $payload['is_locked'] = $is_locked;
        $payload['approval_status'] = $approval_status;
        $payload['is_email_verified'] = $is_email_verified;
        $payload['is_phone_verified'] = $is_phone_verified;
        $payload['is_auto_pwd'] = $is_auto_pwd;



        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/admin.user.update', $payload);

    }

    /**
     * @param ApiTester $I
     * @param string $email
     * @param string $password
     * @param string $name
     * @incomplete
     */
    protected function createUser(ApiTester $I, string $email="", string $password="", string $name="")
    {
        $payload = [];
        $payload['email'] = $email;
        $payload['password'] = $password;
        if (!empty($name)) {
            $payload['name'] = $name;
        }

        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/user.create', $payload);

        // there is no simple way to set this through the API (need a special code sent by email)
        $I->updateInDatabase('users', ['is_email_verified' => 1], ['email' => $this->testEmail]);
        $I->cleanup(); // clean redis cache
    }

    /**
     * @param ApiTester $I
     * @param string $email
     * @param string $password
     * @throws Exception
     */
    protected function login(ApiTester $I, ?string $email = null, ?string $password = null)
    {
        $payload = [];
        if ($email !== null) {
            $payload['email'] = $email;
        }
        if ($password !== null) {
            $payload['password'] = $password;
        }

        $I->haveHttpHeader('user-agent', static::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/user.login', $payload);
        if ($token = $I->grabDataFromResponseByJsonPath('$.token')[0] ?? null) {
            $this->jwtToken = $token;
        }
    }

    /**
     * @param ApiTester $I
     * @param string $fsPath
     * @param string $uploadfile
     * @incomplete
     * @throws Exception
     */

    protected function upload(ApiTester $I, string $fsPath,  $uploadfile)
    {

        $param = '?fspath='.$fsPath.'&start=0&complete=1';
        $files =  [
            'file' => [
                'name' => $uploadfile,
                'size' => filesize(codecept_data_dir($uploadfile)),
                'tmp_name' => codecept_data_dir($uploadfile)
            ]];

        $I->deleteHeader('Content-Type');
        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->sendRawPOST('/file.upload'.$param, ['inline' => 0], $files);
    }

    /**
     * @param ApiTester $I
     * @param string $channelName
     * @param string $userEmailsToAdd
     * @incomplete
     * @throws Exception
     */
    protected function createChannel(ApiTester $I, string $channelName="", string $userEmailsToAdd = "")
    {
        $payload = [];
        $payload['channel_name'] = $channelName;

        if (!empty($userEmailsToAdd)) {
            $payload['emails'] = $userEmailsToAdd;
        }

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/channel.create', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $channelid
     * @incomplete
     * @return mixed|null
     */
    protected function getChannelInfo(ApiTester $I, string $channelid="")
    {
        $payload = [];
        $payload['channel_id'] = $channelid;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        return $I->sendRawGET('/channel.info', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $fsPath
     * @param string $versionid
     * @incomplete
     * @throws Exception
     */

    protected function download(ApiTester $I, string $fsPath,  $versionid="")
    {

        $param = '?fspath='.$fsPath.'&token='.$this->jwtToken.'&versionid='.$versionid;

        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->stopFollowingRedirects();
        $I->sendRawGET('/file.download'.$param);

    }

    /**
     * @param ApiTester $I
     * @param string $fsName
     * @param string $fsParent
     * @incomplete
     * @throws Exception
     */

    protected function createfolder(ApiTester $I, string $fsName, string $fsParent)
    {

        $payload = [];
        $payload['fsparent'] = $fsParent;
        $payload['fsname'] = $fsName;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/file.create', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $fsPath
     * @incomplete
     * @throws Exception
     */

    protected function getFileList(ApiTester $I, string $fsPath)
    {

        $param = '?fspath='.$fsPath;


        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->sendRawGET('/file.list'.$param);
    }

    /**
     * @param ApiTester $I
     * @param string $fsPath
     * @incomplete
     * @throws Exception
     */

    protected function deleteFile(ApiTester $I, string $fsPath)
    {

        $payload = [];
        $payload['fspath'] = $fsPath;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/file.delete', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $fsFromPath
     * @param string $fsToPath
     * @incomplete
     * @throws Exception
     */

    protected function copyFile(ApiTester $I, string $fsFromPath, string $fsToPath)
    {

        $payload = [];
        $payload['fsfrompath'] = $fsFromPath;
        $payload['fstopath'] = $fsToPath;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/file.copy', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $fsFromPath
     * @param string $fsToPath
     * @incomplete
     * @throws Exception
     */

    protected function moveFile(ApiTester $I, string $fsFromPath, string $fsToPath)
    {

        $payload = [];
        $payload['fsfrompath'] = $fsFromPath;
        $payload['fstopath'] = $fsToPath;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/file.move', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $fsPath
     * @incomplete
     * @throws Exception
     */

    protected function getFileInfo(ApiTester $I, string $fsPath)
    {

        $param = '?fspath='.$fsPath;


        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->sendRawGET('/file.info'.$param);
    }

    /**
     * @param ApiTester $I
     * @param string $fsPath
     * @incomplete
     * @throws Exception
     */

    protected function getFileVersions(ApiTester $I, string $fsPath)
    {

        $param = '?fspath='.$fsPath;


        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->sendRawGET('/file.versions'.$param);
    }

    /**
     * @param ApiTester $I
     * @param string $fsPath
     * @param int $width
     * @param int $height
     * @incomplete
     */

    protected function getFileThumb(ApiTester $I, string $fsPath, int $width, int $height)
    {

        $param = '?fspath='.$fsPath.'&width='.$width.'&height='.$height;


        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->sendRawGET('/file.thumb'.$param);
    }


     public function testUploadSuccess(ApiTester $I)
     {
         $this->login($I, $this->testEmail, $this->password);
         $I->seeResponseCodeIsSuccessful();
         $I->seeResponseIsJson();

         // Get Channel info
         $this->getChannelInfo($I, $this->channelId);
         $I->seeResponseCodeIsSuccessful();

         $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        // $team_id = $I->grabDataFromResponseByJsonPath('$channel.team_id')[0];
        // var_dump($team_id);

         $filename = "testfile.txt";

         $parentPath = "/f/51010000";

         $this->upload($I, $parentPath, $filename);
         $I->seeResponseCodeIsSuccessful();
         $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

         //Clear data

         $fsPath = $parentPath .'/'. $filename;
         $this->deleteFile($I, $fsPath);
         $I->seeResponseCodeIsSuccessful();
         $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

     }


    public function testUploadWithoutFspath(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Get Channel info
        $this->getChannelInfo($I, $this->channelId);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        // $team_id = $I->grabDataFromResponseByJsonPath('$channel.team_id')[0];
        // var_dump($team_id);

        $filename = "testfile.txt";

        //$parentPath = "/f/51010000";

        $this->upload($I, '', $filename);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'The Fspath field is required'], 'fspath' => 'The Fspath field is required']);

    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testDownload(ApiTester $I)
     {
         $this->login($I, $this->testEmail, $this->password);
         $I->seeResponseCodeIsSuccessful();
         $I->seeResponseIsJson();

         $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];


         $filename = "testfile.txt";

         $parentPath = $location;

         $this->upload($I, $parentPath, $filename);
         $I->seeResponseCodeIsSuccessful();
         $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


         $parentPath = $location.'/'.$filename;

         $filePath = codecept_data_dir($filename);
         $savePath = codecept_data_dir("downloaded.txt");

         //Test Download
         $this->download($I, $parentPath);

         $I->seeResponseCodeIs('302');
         $loc = $I->grabHttpHeader('Location');
         $I->deleteHeader('authorization');
         $I->sendRawGET($loc);
         $data = $I->grabResponse();

         $fp = fopen($savePath, 'w');
         $file = fwrite($fp, $data);
         fclose($fp);

         $output = file_get_contents($savePath);
         $expected = file_get_contents($filePath);
         $I->assertEquals($output, $expected);
         unlink($savePath);

         //Clear data

         $fsPath = $location .'/'. $filename;
         $this->deleteFile($I, $fsPath);
         $I->seeResponseCodeIsSuccessful();
         $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

     }

    public function testDownloadWithVersionId(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];


        $filename = "versiontest.txt";

        $parentPath = $location;

        //clear data before test
        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //No version test
        $fsPath = $location.'/'.$filename;
        $this->getFileVersions($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true],'file' => []]);

        //edit the file
        $filePath = codecept_data_dir($filename);
        $fp = fopen($filePath, 'w');
        $file = fwrite($fp, "Test123456789");
        fclose($fp);

        //upload again
        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $parentPath = $location.'/'.$filename;

        $this->getFileVersions($I, $parentPath);
        $getVersionId = $I->grabDataFromResponseByJsonPath('$file')[0][0]['versionidentifier'];

        $filePath = codecept_data_dir($filename);
        $savePath = codecept_data_dir("downloaded.txt");

        //Test Download
        $this->download($I, $parentPath,$getVersionId);

        $I->seeResponseCodeIs('302');
        $loc = $I->grabHttpHeader('Location');
        $I->deleteHeader('authorization');
        $I->sendRawGET($loc);
        $data = $I->grabResponse();

        $fp = fopen($savePath, 'w');
        $file = fwrite($fp, $data);
        fclose($fp);

        $output = file_get_contents($savePath);
        $expected = file_get_contents($filePath);
        $I->assertNotEquals($output, $expected);
        unlink($savePath);

        //Clear data

        $filePath = codecept_data_dir($filename);
        $fp = fopen($filePath, 'w');
        $file = fwrite($fp, "TEST File");
        fclose($fp);

        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

    public function testDownloadForInvalidPath(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];


        $filename = "testfile.txt";

        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        $parentPath = $location.'/abc/'.$filename;

        $filePath = codecept_data_dir($filename);
        $savePath = codecept_data_dir("downloaded.txt");

        //Test Download
        $this->download($I, $parentPath, $savePath);

        //$I->seeResponseCodeIsClientError();
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

        //Clear data

        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

        public function testCreateFolder(ApiTester $I)
        {
            $this->login($I, $this->testEmail, $this->password);
            $I->seeResponseCodeIsSuccessful();
            $I->seeResponseIsJson();

            $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

            //Create a folder
            $fsName = "test";
            $fsParent = $location;
            $this->createfolder($I, $fsName, $fsParent);

            $I->seeResponseCodeIsSuccessful();
            $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

            //Clear data

            $fsPath = $fsParent .'/'. $fsName;
            $this->deleteFile($I, $fsPath);
            $I->seeResponseCodeIsSuccessful();
            $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        }

        public function testCreateFolderWithoutParentPath(ApiTester $I)
        {
            $this->login($I, $this->testEmail, $this->password);
            $I->seeResponseCodeIsSuccessful();
            $I->seeResponseIsJson();

            $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

            //Create a folder
            $fsName = "test";

            $this->createfolder($I, $fsName, '');

            $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'The Fsparent field is required'], 'fsparent' => 'The Fsparent field is required']);

        }

        public function testCreateFolderWithoutFolderName(ApiTester $I)
        {
            $this->login($I, $this->testEmail, $this->password);
            $I->seeResponseCodeIsSuccessful();
            $I->seeResponseIsJson();

            $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

            //Create a folder
            $fsParent = $location;
            $this->createfolder($I, '', $fsParent);

            $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'The Fsname field is required'], 'fsname' => 'The Fsname field is required']);

        }

        public function testFileList(ApiTester $I)
        {
            $this->login($I, $this->testEmail, $this->password);
            $I->seeResponseCodeIsSuccessful();
            $I->seeResponseIsJson();

            $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

            //Upload a file
            $filename = "testfile.txt";
            $parentPath = $location;

            $this->upload($I, $parentPath, $filename);
            $I->seeResponseCodeIsSuccessful();
            $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


            //Get File List
            $fsPath = $location;
            $this->getFileList($I, $fsPath);

            $I->seeResponseCodeIsSuccessful();
            $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 3]);

            $getFileName = $I->grabDataFromResponseByJsonPath('$files')[0][2]['name'];

            $getFullPath = $I->grabDataFromResponseByJsonPath('$files')[0][2]['fullpath'];

            $I->assertEquals($getFileName, $filename);
            $I->assertEquals($getFullPath, $location.'/'.$filename);

            //Clear data

            $fsPath = $location .'/'. $filename;
            $this->deleteFile($I, $fsPath);
            $I->seeResponseCodeIsSuccessful();
            $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        }

    public function testFileListForInvalidPath(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Get File List for Invalid Path
        $fsPath = $location.'/abcd';
        $this->getFileList($I, $fsPath);

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Invalid Path: Storage Error']]);

    }

    public function testCopyFile(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Create a folder
        $folderName = "test";
        $fsParent = $location;
        $this->createfolder($I, $folderName, $fsParent);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Copy file to test folder

        $fsFromPath = $location.'/'.$filename;
        $fsToPath = $location .'/'.$folderName.'/'.$filename;
        $this->copyFile($I, $fsFromPath, $fsToPath);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        //Get File List
        $testFolderPath = $location.'/'.$folderName;
        $this->getFileList($I, $testFolderPath);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 1]);

        $getFileName = $I->grabDataFromResponseByJsonPath('$files')[0][0]['name'];

        $getFullPath = $I->grabDataFromResponseByJsonPath('$files')[0][0]['fullpath'];

        $I->assertEquals($getFileName, $filename);
        $I->assertEquals($getFullPath, $testFolderPath.'/'.$filename);

        //Clear data

        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $fsPath = $location .'/'. $folderName;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


    }

    public function testCopyFileWithInvalidFromPath(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Create a folder
        $folderName = "test";
        $fsParent = $location;
        $this->createfolder($I, $folderName, $fsParent);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Copy file without From Path

        $fsFromPath = $location.'/'.$filename;
        $fsToPath = $location .'/'.$folderName.'/'.$filename;
        $this->copyFile($I, '', $fsToPath);

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'The Fsfrompath field is required'], 'fsfrompath' => 'The Fsfrompath field is required']);

        //Copy file Invalid From Path

        $fsFromPath = $location.'/abcde/'.$filename;
        $fsToPath = $location .'/'.$folderName.'/'.$filename;
        $this->copyFile($I, $fsFromPath, $fsToPath);

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Storage Error']]);

        //Clear data

        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $fsPath = $location .'/'. $folderName;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


    }

    public function testCopyFileWithInvalidToPath(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Create a folder
        $folderName = "test";
        $fsParent = $location;
        $this->createfolder($I, $folderName, $fsParent);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Copy file without To Path

        $fsFromPath = $location.'/'.$filename;
        $fsToPath = $location .'/'.$folderName.'/'.$filename;
        $this->copyFile($I, $fsFromPath, '');

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'The Fstopath field is required'], 'fstopath' => 'The Fstopath field is required']);

        //Copy file Invalid To Path

        $fsFromPath = $location.'/'.$filename;
        $fsToPath = $location .'/abcde/'.$folderName.'/'.$filename;
        $this->copyFile($I, $fsFromPath, $fsToPath);

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Storage Error']]);

        //Clear data

        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $fsPath = $location .'/'. $folderName;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

    public function testMoveFile(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Create a folder
        $folderName = "test";
        $fsParent = $location;
        $this->createfolder($I, $folderName, $fsParent);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Move file to test folder

        $fsFromPath = $location.'/'.$filename;
        $fsToPath = $location .'/'.$folderName.'/'.$filename;
        $this->moveFile($I, $fsFromPath, $fsToPath);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 3]);

        //Get File List
        $testFolderPath = $location.'/'.$folderName;
        $this->getFileList($I, $testFolderPath);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 1]);

        $getFileName = $I->grabDataFromResponseByJsonPath('$files')[0][0]['name'];

        $getFullPath = $I->grabDataFromResponseByJsonPath('$files')[0][0]['fullpath'];

        $I->assertEquals($getFileName, $filename);
        $I->assertEquals($getFullPath, $testFolderPath.'/'.$filename);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 3]);


        //Clear data

        $fsPath = $location .'/'. $folderName;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


    }

    public function testMoveFileWithInvalidFromPath(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Create a folder
        $folderName = "test";
        $fsParent = $location;
        $this->createfolder($I, $folderName, $fsParent);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Move file without From Path

        $fsFromPath = $location.'/'.$filename;
        $fsToPath = $location .'/'.$folderName.'/'.$filename;
        $this->moveFile($I, '', $fsToPath);

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'The Fsfrompath field is required'], 'fsfrompath' => 'The Fsfrompath field is required']);

        //Copy file Invalid From Path

        $fsFromPath = $location.'/abcde/'.$filename;
        $fsToPath = $location .'/'.$folderName.'/'.$filename;
        $this->moveFile($I, $fsFromPath, $fsToPath);

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Storage Error']]);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 4]);

        //Clear data

        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $fsPath = $location .'/'. $folderName;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


    }

    public function testMoveFileWithInvalidToPath(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location= $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Create a folder
        $folderName = "test";
        $fsParent = $location;
        $this->createfolder($I, $folderName, $fsParent);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Move file without To Path

        $fsFromPath = $location.'/'.$filename;
        $fsToPath = $location .'/'.$folderName.'/'.$filename;
        $this->moveFile($I, $fsFromPath, '');

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'The Fstopath field is required'], 'fstopath' => 'The Fstopath field is required']);

        //Move file Invalid To Path

        $fsFromPath = $location.'/'.$filename;
        $fsToPath = $location .'/abcde/'.$folderName.'/'.$filename;
        $this->moveFile($I, $fsFromPath, $fsToPath);

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Storage Error']]);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 4]);

        //Clear data

        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $fsPath = $location .'/'. $folderName;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

    public function testDeleteFile(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location = $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Create a folder
        $folderName = "test";
        $fsParent = $location;
        $this->createfolder($I, $folderName, $fsParent);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 4]);

        //Delete File
        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 3]);

        //Delete Folder
        $fsPath = $location .'/'. $folderName;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 2]);


    }

    public function testDeleteFileWithInvalidPath(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location = $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Create a folder
        $folderName = "test";
        $fsParent = $location;
        $this->createfolder($I, $folderName, $fsParent);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 3]);


        //Delete Folder Invalid path
        $fsPath = $location .'/abcd/'. $folderName;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 3]);

        //Delete Folder without fsPath
        $this->deleteFile($I, '');
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 3]);

        //Delete Folder
        $fsPath = $location .'/'. $folderName;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //Get File List Check the original folder location
        $this->getFileList($I, $location);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true], 'total' => 2]);


    }

    public function testFileInfoForFolder(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location = $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Create a folder
        $folderName = "test";
        $fsParent = $location;
        $this->createfolder($I, $folderName, $fsParent);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //File Info Folder
        $fsPath = $location . '/' . $folderName;
        $this->getFileInfo($I, $fsPath);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $getFileName = $I->grabDataFromResponseByJsonPath('$file.name')[0];
        $getType = $I->grabDataFromResponseByJsonPath('$file.type')[0];
        $getFullPath = $I->grabDataFromResponseByJsonPath('$file.fullpath')[0];

        $I->assertEquals($getFileName, $folderName);
        $I->assertEquals($getType, 'folder');
        $I->assertEquals($getFullPath, $fsPath);

        //Clear Data
        $fsPath = $location . '/' . $folderName;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }


    public function testFileInfoForFile(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location = $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //File Info File
        $fsPath = $location.'/'.$filename;
        $this->getFileInfo($I, $fsPath);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $getFileName = $I->grabDataFromResponseByJsonPath('$file.name')[0];
        $getType = $I->grabDataFromResponseByJsonPath('$file.type')[0];
        $getFullPath = $I->grabDataFromResponseByJsonPath('$file.fullpath')[0];

        $I->assertEquals($getFileName, $filename);
        $I->assertEquals($getType, 'file');
        $I->assertEquals($getFullPath, $fsPath);

        //Clear Data
        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

    public function testFileInfoForInvalidPath(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location = $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //File Info for Invalid Path
        $fsPath = $location.'/abcd/'.$filename;
        $this->getFileInfo($I, $fsPath);

        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

        //File Info Without FsPath
        $this->getFileInfo($I, '');

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'The Fspath field is required'], 'fspath' => 'The Fspath field is required']);

        //Clear Data
        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

    public function testFileVersions(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location = $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //GetFileVersions
        $fsPath = $location.'/'.$filename;
        $this->getFileVersions($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true],'file' => []]);


        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //GetFileVersions
        $fsPath = $location.'/'.$filename;
        $this->getFileVersions($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $files = $I->grabDataFromResponseByJsonPath('$file');
        //var_dump($files);

        $getFileName = $I->grabDataFromResponseByJsonPath('$file')[0][0]['name'];
        $getType = $I->grabDataFromResponseByJsonPath('$file')[0][0]['type'];
        $getSize = $I->grabDataFromResponseByJsonPath('$file')[0][0]['size'];
        $getVersionId = $I->grabDataFromResponseByJsonPath('$file')[0][0]['versionidentifier'];

        $I->assertEquals($getFileName, $filename);
        $I->assertNotEmpty($getVersionId);

        //Clear Data
        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

    public function testFileVersionsForInvalidPath(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location = $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "testfile.txt";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

       /* //GetFileVersions for invalid path
        $fsPath = $location.'/abcd/'.$filename;
        $this->getFileVersions($I, $fsPath);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);*/

        //GetFileVersions for no path
        $fsPath = $location.'/'.$filename;
        $this->getFileVersions($I, '');
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'The Fspath field is required'], 'fspath' => 'The Fspath field is required']);


        //Clear Data
        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

   /* public function testFileThumb(ApiTester $I)
    {
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $location = $I->grabDataFromResponseByJsonPath('$user.user_fs_roots')[0][0]['location'];

        //Upload a file
        $filename = "Image.jpg";
        $parentPath = $location;

        $this->upload($I, $parentPath, $filename);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $fsPath = $location .'/'. $filename;
        $this->getFileThumb($I, $fsPath, 128, 128);
        $I->seeResponseCodeIsSuccessful();

        $data = $I->grabResponse();

        $savePath = codecept_data_dir("testImage.jpg");
        $fp = fopen($savePath, 'w');
        $file = fwrite($fp, $data);
        fclose($fp);
        var_dump(md5_file($savePath));

        unlink('testImage.jpg');

        //Clear Data
        $fsPath = $location .'/'. $filename;
        $this->deleteFile($I, $fsPath);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


    }*/


}