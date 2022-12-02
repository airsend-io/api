<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Service\Storage\Backstore;

use CodeLathe\Service\Storage\Shared\RequestObject;
use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\Shared\StorageObject;
use CodeLathe\Service\Storage\StorageService;
use CodeLathe\Service\Storage\Util\StorageServiceUtility;
use CodeLathe\Service\Storage\Config\StorageServiceConfig;
use Aws\S3\S3Client;

/**
 * S3 storage implementation
 * Class S3BackstoreImpl
 * @package CodeLathe\Service\Storage\Backstore
 * @deprecated Moved inside the MysqlS3 implementation
 */
class S3BackstoreImpl extends AbstractBackstore
{
    // Server side managed enc
    const S3ENCTYPE_TYPE_S3MANAGED = '0';
    // Server side managed enc with KMS key
    const S3ENCTYPE_TYPE_KMSSSE = '1';
    // Server side managed enc with Customer Key
    const S3ENCTYPE_TYPE_SSE_C = '2';

    private $configured = false;
    /** @var S3Client */
    private $s3connection = null;

    //function to make connection to a S3 bucket

    /**
     * Function to connect to S3 bucket
     * @return bool
     */
    public function makeConnection(): bool
    {
        $VERSION = "2006-03-01";

        if ($this->configured === TRUE) {
            return TRUE;
        }
        
        //StorageService::logger()->debug(__CLASS__."/".__FUNCTION__.": Making connection to bucket: " . $this->storageConfig['bucketname']);

        //create a new config
        $config = array(
            'version'   => $VERSION,
            'credentials' => array (
                'key'    => $this->storageConfig['key'],
                'secret' => $this->storageConfig['secret']
            ),
            'region' => $this->storageConfig['region']
        );

        //add endpoint details if specified
        if(!empty($this->storageConfig['endpoint']))
        {
            $config += array(
                    "endpoint" => $this->storageConfig['endpoint']
                );
        }

        //add proxy if applicable
        if(!empty($this->storageConfig['proxy']))
        {
            $config += array(
                'request.options' => array(
                    'proxy' => $this->storageConfig['proxy']
                )
            );
        }

        //set cacert path if available
        $config += array(
            'http' => array(
                'verify' => $this->storageConfig['cacertpath']??false
            )
        );

        //create a S3 client
        $this->s3connection = S3Client::factory($config);

        $success = $this->s3connection->doesBucketExist($this->storageConfig['bucketname'], false);
        if($success)
        {
            //bucket exists
            //StorageService::logger()->debug(__CLASS__."/".__FUNCTION__.": Successful connecting to bucket: " . $this->storageConfig['bucketname']);
            $this->configured = TRUE;
            return TRUE;

        }
        else
        {
            //bucket doesn't exists
            StorageService::logger()->error(__CLASS__."/".__FUNCTION__.": Bucket doesn't exist: " . $this->storageConfig['bucketname']);
            return FALSE;
        }
    }

    /**
     * @param string $siteId
     * @param StorageObject $fileObject
     * @param StorageObject|null $copyFromObject
     * @return bool
     */
    public function storeFile(StorageObject $fileObject, StorageObject $copyFromObject = null) : bool
    {
        if(isset($copyFromObject)){
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Creating file from existing s3 object");
        } else{
            $incomingFilePath = $fileObject->getIncomingDataAsFile();
            if(!isset($incomingFilePath)){
                return FALSE;
            }
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Creating file from incoming data source");
        }

        $folderprefix = StorageServiceConfig::CLSTORAGE_S3_TOPFOLDERPREFIX;
        $siteId = $fileObject->getObjectSiteId();
        if(empty($siteId)){
            $siteId = "default";
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": No siteid specified. Using 'default'");
        } else {
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Siteid specified : $siteId");
        }

        //Set file store path
        $fileStorePath = rtrim($folderprefix,'/').'/'.$siteId.'/'.$fileObject->getFileStorageName();
        $fileObject->setFileStoragePath($fileStorePath);

        if($fileObject->isSegmented()){
            //Segmented flow
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Segmented flow ".$fileObject->getObjectFullPath());
            $fileSize = (double) filesize($incomingFilePath);
            $multipartUploadThresholdSize = $this->getMultiPartChunkSize();
            if($fileSize >= $multipartUploadThresholdSize){
                // chunk size has to be 5MB or higher in order to use S3 multipart support
                $success = $this->startS3MultiPartUploadSequence($fileObject, $incomingFilePath);
                $fileObject->closeIncomingDataStream(); //closes if incoming file is stream or noop if its path
                $fileObject->setFileSize($fileSize);
                return $success;

            } else{
                // chunk size is less than threshold, collect upload parts until threshold reaches
                $uploadPartCache = $fileObject->getUploadPartCacheFile();
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Creating local cache $uploadPartCache until size is greater than ". $multipartUploadThresholdSize ." bytes");

                //delete any existing part file and copy
                if (file_exists($uploadPartCache)) {
                    unlink($uploadPartCache);
                }
                $success = copy($incomingFilePath, $uploadPartCache);
                if($success){
                    //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                    //    .": Successfully saved upload part to local cache". $uploadPartCache);
                    $fileObject->closeIncomingDataStream(); //closes if incoming file is stream or noop if its path
                    $fileObject->setFileSize($fileSize);
                    return TRUE;
                } else{
                    StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                        .": Unable to save part to local cache". $uploadPartCache);
                    return FALSE;
                }
            }
        }
        else
        {
            //Non segmented flow
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Non Segmented flow ".$fileObject->getObjectFullPath());

            if(isset($copyFromObject)){
                //object to be created from an existing S3 object
                // File exists in storage already. Copy it to the new path
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Copy source exists in S3. Performing S3 copy");

                $s3CopyFromPath = $this->getS3CopySourceStoreFile($copyFromObject);
                if(!isset($s3CopyFromPath)){
                    //StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": "
                    //        .": Unable to find S3 storage path for source file " . $copyFromObject->getObjectFullPath());
                    return FALSE;
                }

                //setup S3 copy command params
                $params = array(
                    'Bucket' => $this->storageConfig['bucketname'],
                    'CopySource' => $s3CopyFromPath,
                    'Key' => $fileObject->getFileStoragePath()
                );

                //add additional attributes as applicable
                $this->addCommonBucketAttributes($params);
                $this->addSSECAttributes($params);


                try {
                    $result = $this->s3connection->copyObject($params);
                    if ($result == NULL) {
                        StorageService::logger()->error(F__CLASS__.":".__FUNCTION__.": "
                            .": Failed copying file from S3 bucket!".print_r($params,true));
                        return FALSE;
                    }
                }catch (\core\framework\TonidoCloudException $e) {
                    StorageService::logger()->error(F__CLASS__.":".__FUNCTION__.": "
                        . ": Exception copying object: Failed copying file inside S3 bucket!"
                        . print_r($params,true));
                    return FALSE;
                }

                // Get stored object attributes
                $fileObj = $this->getObjectAttributes($fileObject);
                $fileSize = $fileObj->get("ContentLength");
                $fileObject->setFileSize($fileSize);
                return TRUE;
            } else{
                //object to be created from a new file
                $fileSize = filesize($incomingFilePath);
                $result = $this->s3DirectUpload($incomingFilePath, $fileStorePath);
                $fileObject->closeIncomingDataStream(); //closes if incoming file is stream or noop if its path

                //StorageService::logger()->debug(print_r($result, true));
                if ($result == NULL) {
                    StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Failed uploading file to S3 bucket!");
                    return FALSE;
                } else{
                    //object stored successfully. update the storage object with attributes from storage layer
                    //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Successfully uploaded to S3 ".$fileObject->getObjectFullPath());
                    $fileObject->setFileSize($fileSize);
                    $fileObject->setFileStoragePath($fileStorePath);
                    return TRUE;
                }
            }
        }
    }

    public function addToExistingFile(StorageObject $fileObject) : bool
    {
        $incomingFilePartPath = $fileObject->getIncomingDataAsFile();
        if(!isset($incomingFilePartPath)){
            return FALSE;
        }
        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Appending to file "
        //    ."from incoming data source");


        $incomingFilePartSize = (float) filesize($incomingFilePartPath);
        $multipartUploadThresholdSize = $this->getMultiPartChunkSize();
        if($incomingFilePartSize >= $multipartUploadThresholdSize){
            // chunk size has to be 5MB or higher in order to use S3 multipart support
            $success = $this->continueS3MultiPartUploadSequence($fileObject, $incomingFilePartPath);
            $fileObject->closeIncomingDataStream(); //closes if incoming file is stream or noop if its path
            return $success;

        } else{
            // chunk size is less than threshold, collect upload parts until threshold reaches
            $uploadCacheFilePath = $fileObject->getUploadPartCacheFile();
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            //    .": Appending to local cache until size is greater than ". $multipartUploadThresholdSize ." bytes");

            $success = file_put_contents($uploadCacheFilePath,
                file_get_contents($incomingFilePartPath), FILE_APPEND);
            if(!$success){
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                    .": Unable to append part to local cache". $uploadCacheFilePath);
                $fileObject->closeIncomingDataStream(); //closes if incoming file is stream or noop if its path
                return FALSE;
            }

            //copy successful to local cache
            $uploadCacheSize = (float) filesize($uploadCacheFilePath);
            //check if is this an continuation of an existing upload
            $uploadMetaData = $fileObject->getBackstoredata();

            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            //    .": Successfully appended upload part to local cache :". $uploadCacheFilePath
            //    .", resulting in cached size: ".$uploadCacheSize);
            if($uploadCacheSize >= $multipartUploadThresholdSize){
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Upload cache exceeded threshold, uploading :". $uploadCacheFilePath);
               if (isset($uploadMetaData['uploadid'])) {
                    //existing upload, so continue the upload
                    $success = $this->continueS3MultiPartUploadSequence($fileObject, $incomingFilePartPath);
                    $fileObject->closeIncomingDataStream(); //closes if incoming file is stream or noop if its path
                    return $success;
                } else{
                    //new upload, start a fresh upload sequence
                    $success = $this->startS3MultiPartUploadSequence($fileObject, $incomingFilePartPath);
                    $fileObject->closeIncomingDataStream(); //closes if incoming file is stream or noop if its path
                    return $success;
                }
            } else {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                 //   .": Upload cache didn't exceed threshold :". $uploadCacheSize . "<" . $multipartUploadThresholdSize);
                if($fileObject->isComplete()){
                    if (isset($uploadMetaData['uploadid'])) {
                        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                        //    .": Cache not exceed, but final chunk of an existing upload received. "
                        //    ."Uploading cache and finalizing :". $uploadCacheFilePath);
                        $success = $this->continueS3MultiPartUploadSequence($fileObject, $incomingFilePartPath);
                        $fileObject->closeIncomingDataStream(); //closes if incoming file is stream or noop if its path
                        $fileObject->removeUploadPartCacheFile();
                        return $success;
                    } else {
                        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                        //    .": Cache not exceed, but final chunk of a locally collected file parts received. "
                        //    ."Uploading the cache and finalizing :". $uploadCacheFilePath);

                        $result = $this->s3DirectUpload($uploadCacheFilePath, $fileObject->getFileStoragePath());
                        $fileObject->closeIncomingDataStream(); //closes if incoming file is stream or noop if its path
                        $fileObject->removeUploadPartCacheFile();
                        if ($result == NULL) {
                            StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                                .": Failed uploading file to S3 bucket: ". $fileObject->getObjectFullPath());
                            return FALSE;
                        } else{
                            //object stored successfully. update the storage object with attributes from storage layer
                            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                            //    .": Successfully uploaded to S3 ".$fileObject->getObjectFullPath());
                            $fileObject->setFileSize($uploadCacheSize);
                            return TRUE;
                        }
                    }
                } else{
                    //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                     //   .": Upload not completed. Updatinfg size ".$fileObject->getObjectFullPath());
                    $fileObject->setFileSize($uploadCacheSize);
                    return TRUE;
                }
            }
        }
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    public function deleteFile(StorageObject $fileObject) : bool
    {
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Deleting file from S3 : ".$fileObject->getFileStoragePath());

        $params = array(
            'Bucket' => $this->storageConfig['bucketname'],
            'Key' => $fileObject->getFileStoragePath(),
        );
        $this->addCommonBucketAttributes($params);

        $result = $this->s3connection->deleteObject($params);

        if ($result != NULL) {
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Deleted file from S3: ".$fileObject->getFileStoragePath());
            return TRUE;
        }

        StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Error deleting file from S3 : ".$fileObject->getFileStoragePath());
        return FALSE;

    }

    /**
     * @param StorageObject $fileObject
     * @param RequestObject $requestObject
     */
    public function createDownloadResponse(StorageObject $fileObject, RequestObject $requestObject): ResponseObject
    {
        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
        //    .": Sending file " .$fileObject->getObjectFullPath());

        if ($this->isSSEC()) {
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //. " : S3 redirect not allowed for SSE-C encryption");
            $redirectDisabled = TRUE;
        } else{
            $redirectDisabled = !$requestObject->isDownloadRedirect();
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            //    .": S3 download redirect state set to " . ($redirectDisabled ?"DISABLED":"ENABLED")  );
        }


        //We dont want to provide direct S3 link, if the requested service is docconvert
        //Since that will cause pdf.js to error out.
        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Docconvert request state:"
            //.$requestObject->isDownloadADocconvert());
        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Client redirect support state:"
           // .$requestObject->isDownloadRedirect());

        $agent = StorageServiceUtility::parseUserAgent(StorageServiceUtility::getUserAgent());
        if( $requestObject->isDownloadAsStream()){
            return $this->s3DownloadFileAsStream($fileObject, $requestObject);
        } else if (!$requestObject->isDownloadADocconvert() && !$redirectDisabled) {
            return $this->s3DirectDownload($fileObject);
        } else {
            return $this->s3StoreAndForwardDownload($fileObject, $requestObject);
        }
    }

    /**
     * @param $srcFile
     * @param $key
     * @return mixed
     */
    private function s3DirectUpload($srcFile, $key){
        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Single shot upload of local file " .$srcFile . " to " .$key);

        $params = array(
            'Bucket' => $this->storageConfig['bucketname'],
            'Key' => $key,
            'SourceFile' => $srcFile);
        $this->addCommonBucketAttributes($params);

        $result = $this->s3connection->putObject($params);

        //wait for the object to appear in S3
        unset($params['SourceFile']);
        $this->s3connection->waitUntil('ObjectExists',$params );

        return $result;
    }

    /**
     * @param StorageObject $fileObject
     */
    private function s3DirectDownload(StorageObject $fileObject): ResponseObject
    {

        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
         //   .": Direct download to client " .$fileObject->getObjectFullPath());

        // Get the actual presigned-url
        $signedUrl = $this->createSignedURL($fileObject);

        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Signed direct download URL:".$signedUrl);

        return ResponseObject::createDownloadRedirectResponse($signedUrl);
    }

    /**
     * @param StorageObject $fileObject
     * @param RequestObject $requestObject
     */
    private function s3StoreAndForwardDownload(StorageObject $fileObject, RequestObject $requestObject):ResponseObject
    {

        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Store and forward download to client "
         //   .$fileObject->getObjectFullPath());

        $contentType = $requestObject->getDownloadContentType()??$fileObject->getHTTPContentType();

        $rangesResponseArray = array();
        $rangeArray = $requestObject->getDownloadRequestRangeArray();
        foreach ($rangeArray as $range) {
            //figure out download piece from range (if set)
            list($seek_start, $seek_end) = explode('-', $range, 2);
            $downloadrange = $this->sendRangeToClient($fileObject, $seek_start,
                $seek_end, $requestObject->downloadAsAttachment());
            $rangesResponseArray[] = $downloadrange;
        }
        return ResponseObject::createDownloadRangesResponse($rangesResponseArray);
    }

    /**
     * @param StorageObject $fileObject
     * @param RequestObject $requestObject
     */
    private function s3DownloadFileAsStream(StorageObject $fileObject, RequestObject $requestObject):ResponseObject
    {

        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Download file as stream"
        //   .$fileObject->getObjectFullPath());
        set_time_limit(0);
        ignore_user_abort(true);

        //create a temporary file
        $tmpfile = tempnam(StorageServiceUtility::getTempFolder(),"s3d");
        $tmpfp = fopen($tmpfile, 'w');
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": download to local temp file : ".$tmpfile);

        //store s3 object to the temp file
        $params = array(
            'Bucket'        => $this->storageConfig['bucketname'],
            'Key'           => $fileObject->getFileStoragePath(),
        );
        $this->addCommonBucketAttributes($params);
        $result = $this->s3connection->getObject($params);
        // Go to the beginning of stream
        $result['Body']->rewind();
        return ResponseObject::createDownloadAsStreamResponse($result['Body']);
    }

    /**
     * @param StorageObject $fileObject
     * @param int $seek_start
     * @param int $seek_end
     * @param bool $isAttachment
     */
    private function sendRangeToClient(StorageObject $fileObject, string $seek_start_str, string $seek_end_str, bool $isAttachment)
    {

        //set start and end based on range (if set), else set defaults
        //also check for invalid ranges.
        $seek_end = (empty($seek_end_str))
            ? ($fileObject->getFileSize() - 1) : min(abs(floatval($seek_end_str)), ($fileObject->getFileSize() - 1));
        $seek_start = (empty($seek_start_str) || $seek_end < abs(floatval($seek_start_str)))
            ? 0 : max(abs(floatval($seek_start_str)), 0);

        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": ".$fileObject->getFileStoragePath()
            ." :".$seek_start." ==> ".$seek_end);

        //Check if this is within allowed threshold of storeandforward
        $limit = intval(StorageServiceConfig::CLSTORAGE_STOREANDFORWARD_DOWNLOAD_LIMIT);
        if ( $limit > 0) {
            if ($fileObject->getFileSize() > $limit ) {
                StorageService::logger()->error(__CLASS__  ." : ". __FUNCTION__ . " : File size :  " . $fileObject->getFileSize()
                    . " exceeds limit $limit bytes. Rejecting download!");
                return;
            }
        }

        set_time_limit(0);
        ignore_user_abort(true);

        //create a temporary file
        $tmpfile = tempnam(StorageServiceUtility::getTempFolder(),"s3d");
        $tmpfp = fopen($tmpfile, 'w');
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": download to local temp file : ".$tmpfile);

        //store s3 object to the temp file
        $params = array(
            'Bucket'        => $this->storageConfig['bucketname'],
            'Key'           => $fileObject->getFileStoragePath(),
            'Range'         => "bytes=".$seek_start . '-' . $seek_end,
            'SaveAs'        => $tmpfp
        );
        $this->addCommonBucketAttributes($params);
        $result = $this->s3connection->getObject($params);
        fclose($tmpfp);

        //set download headers
        $rangeData = ResponseObject::getDownloadHeaders($fileObject, $seek_start, $seek_end, $isAttachment);
        //send file to client
        if (isset($result)){
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Have local temp file "
                .$result->get("ContentLength")." bytes");
            //readfile($tmpfile);
            $rangeData['tmpfile'] = $tmpfile;
            return $rangeData;
        }

        return NULL;
        //TODO: make sure this is deleted
        //if (file_exists($tmpfile)) {
        //    unlink($tmpfile);
        //}
    }

    /*
     * S3 Utility functions
     */
    /**
     * @param StorageObject $s3Object
     * @return string|null
     */
    protected function getS3CopySourceStoreFile(StorageObject $s3Object) : ?string
    {
        $storageZoneConfig = StorageServiceConfig::getStorageZoneConfig($s3Object->getStorageZoneId());
        $storageConfig = $storageZoneConfig['S3']?? NULL;
        return $storageConfig?($storageConfig['bucketname'].'/'.$s3Object->getFileStoragePath()) : NULL;
    }

    /**
     * @param StorageObject $s3Object
     * @return object|null
     */
    protected function getObjectAttributes(StorageObject $s3Object): ?object
    {
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.":".$s3Object->getFileStoragePath());

        $params = array(
            'Bucket' => $this->storageConfig['bucketname'],
            'Key'    => $s3Object->getFileStoragePath()
        );
        $this->addCommonBucketAttributes($params);
        return $this->s3connection->headObject($params);

    }

    /**
     * @param $params
     */
    protected function addCommonBucketAttributes(&$params) {
        if ($this->storageConfig['encryptionenabled']) {

            if ($this->storageConfig['EncryptionType'] === S3BackstoreImpl::S3ENCTYPE_TYPE_S3MANAGED)
            {
                $params['ServerSideEncryption'] = 'AES256';
            }
            else if ($this->storageConfig['EncryptionType'] === S3BackstoreImpl::S3ENCTYPE_TYPE_KMSSSE)
            {
                $params['ServerSideEncryption'] = 'aws:kms';

                if ($this->storageConfig['EncryptionKMSKey'] != "")
                {
                    $params['SSEKMSKeyId'] = $this->encryptionkmskey;
                }
            }
            else if ($this->storageConfig['EncryptionType'] === S3BackstoreImpl::S3ENCTYPE_TYPE_SSE_C)
            {
                $params['SSECustomerAlgorithm'] = 'AES256';
                $params['SSECustomerKey'] = $this->storageConfig['EncryptionCustomerKey'];
                $params['SSECustomerKeyMD5'] = $this->storageConfig['EncryptionCustomerKeyMD5'];
            }
        }

        if ($this->storageConfig['reducedredundancy']) {
            $params['StorageClass'] = 'REDUCED_REDUNDANCY';
        }
    }

    protected function addSSECAttributes(&$params) {
        if ($this->isSSEC()) {
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": SSEC enabled. Adding S3 params");
            $params += array(
                'CopySourceSSECustomerAlgorithm' => 'AES256',
                'CopySourceSSECustomerKey' => $this->encryptioncustkey,
                'CopySourceSSECustomerKeyMD5' => $this->encryptioncustkey64md5
            );
        }
    }

    /**
     * @return bool
     */
    protected function isSSEC() : bool
    {
        if ($this->storageConfig['encryptionenabled']) {
            return ($this->storageConfig['EncryptionType']  === S3BackstoreImpl::S3ENCTYPE_TYPE_SSE_C);
        }
        return FALSE;
    }

    /**
     * @param StorageObject $fileObject
     * @return string
     */
    protected function createSignedURL(StorageObject $fileObject){

        $cmd = $this->s3connection->getCommand('GetObject', array(
            'Bucket' => $this->storageConfig['bucketname'],
            'Key'    => $fileObject->getFileStoragePath(),
            'ResponseContentDisposition' => 'attachment;charset=utf-8;filename="'
                .utf8_encode($fileObject->getName()).'"',
            'ResponseContentType' => $fileObject->getHTTPContentType(),
        ));


        $request = $this->s3connection->createPresignedRequest($cmd, '+2 minutes');

        // Get the actual presigned-url
        $signedUrl = (string) $request->getUri();
        return $signedUrl;
    }

    private function startS3MultiPartUploadSequence(StorageObject $fileObject, string $sourceFileName) : bool
    {
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            .": Creating multipart upload sequence - " . $fileObject->getFileStoragePath()
            . " with source file ".$sourceFileName);

        //configure multipart init request
        $params =  array(
            'Bucket' => $this->storageConfig['bucketname'],
            'Key' => $fileObject->getFileStoragePath()
        );
        $this->addCommonBucketAttributes($params);

        //perform request
        $result = $this->s3connection->createMultipartUpload($params);
        if(!isset($result['UploadId'])){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                .": Unable to initiate multipart upload ". $fileObject->getFileStoragePath());
            return FALSE;
        }

        //Multipart upload initialized successfully, start the upload of 1st chunk
        $uploadId = $result['UploadId'];
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            .": Multipart request initiated with S3 with request id ".$uploadId);

        $chunksize = filesize($sourceFileName);
        $handle = fopen($sourceFileName, 'r');

        //configure upload request using the ID from previous step
        $params = array(
            'Bucket' => $this->storageConfig['bucketname'],
            'Body' => fread($handle,$chunksize),
            'Key'=>  $fileObject->getFileStoragePath(),
            'UploadId' => $uploadId,
            'PartNumber' => 1
        );
        $this->addCommonBucketAttributes($params);

        //perform request
        $result = $this->s3connection->uploadPart($params);
        fclose($handle);
        if (!isset($result))
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                .": Failed uploading multipart upload sequence no: (1) ");
            return FALSE;
        }

        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            .": Uploaded  multipart upload sequence no: (1) ");

        $parts = array();
        $parts[] =  array(
            'PartNumber' => 1,
            'ETag'       => $result['ETag'],
        );

        $fileObject->setBackstoredata( array(
            'size' => floatval($chunksize),
            'containerid' => $this->storageConfig['bucketname'],
            'parts' => $parts,
            'uploadid' => $uploadId,
            'partnum' => 1,
            'storedpath' => $fileObject->getFileStoragePath()
        ));
        return TRUE;
    }


    private function continueS3MultiPartUploadSequence(StorageObject $fileObject, string $sourceFileName) : bool
    {
        $key = $fileObject->getFileStoragePath();
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            .": Continuing multipart upload - " . $fileObject->getFileStoragePath()
            . " with source file ".$sourceFileName);

        //check the multipart upload sequence details
        $uploadMetaData = $fileObject->getBackstoredata();
        if (!isset($uploadMetaData['uploadid']) || !(isset($uploadMetaData['partnum'])))
        {
            // This should never happen. The upload id should have been created from storeFile
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                .":INVALID STATE!. Add to existing file called without calling store file?"
                .print_r($fileObject,true));
            return FALSE;
        }

        $chunksize = filesize($sourceFileName);
        $handle = fopen($sourceFileName, 'r');
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.":[" . $fileObject->getObjectFullPath().
            "]:".$sourceFileName." --> ". $uploadMetaData['storedpath'].":start=".$fileObject->getFileChunkStart()
            ." chunksize=". $chunksize . " partnum = ". $uploadMetaData['partnum']
            ." lastsegment=".(int)$fileObject->isComplete()." uploadid=". $uploadMetaData['uploadid']  );

        //configure upload request using the ID from previous step
        $uploadMetaData['partnum'] = $uploadMetaData['partnum'] + 1;
        $params = array(
            'Bucket' => $this->storageConfig['bucketname'],
            'Body' => fread($handle, $chunksize),
            'Key'=> $uploadMetaData['storedpath'],
            'UploadId' => $uploadMetaData['uploadid'],
            'PartNumber' => $uploadMetaData['partnum']
        );
        $this->addCommonBucketAttributes($params);

        // Upload part
        $result = $this->s3connection->uploadPart($params);
        fclose($handle);
        if (!isset($result))
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                .": Failed uploading multipart upload sequence no: (".$uploadMetaData['partnum'].")");
            return FALSE;
        }
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            .": Uploaded  multipart upload sequence no: (".$uploadMetaData['partnum'].")");

        //update the parts list
        $uploadMetaData['parts'][] =  array(
            'PartNumber' => $uploadMetaData['partnum'],
            'ETag'       => $result['ETag'],
        );
        $fileObject->setBackstoredata($uploadMetaData);
        
        //update file size
        $newFileSize = $fileObject->getFileSize() + $chunksize;
        $fileObject->setFileSize($newFileSize);

        //complete the multipart upload if this is last chunk
        if ($fileObject->isComplete()) {
            StorageService::logger()->debug((__CLASS__.":".__FUNCTION__.": Completing  multipart upload sequence. "
                . "Total Parts = ". $uploadMetaData['partnum']));

            $params = array(
                'Bucket' => $this->storageConfig['bucketname'],
                'Key'      => $uploadMetaData['storedpath'],
                'UploadId' => $uploadMetaData['uploadid'],
                'MultipartUpload' => array(
                    'Parts' => $uploadMetaData['parts'],
                )
            );
            $this->addCommonBucketAttributes($params);

            //mark completion in S3
            $result =  $this->s3connection->completeMultipartUpload($params);
            if (!isset($result))
            {
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                    .": Failed completing multipart upload sequence : ".$fileObject->getObjectFullPath()."");
                return FALSE;
            } else{
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                    .": File upload of  ". $fileObject->getObjectFullPath() . " completed");
                return TRUE;
            }
        }

        return TRUE;
    }

    /**
     * Other utility function
     */
    /**
     * @return float
     */
    protected function getMultiPartChunkSize() : float
    {
        $sizeInMB = StorageServiceConfig::CLSTORAGE_S3_MULTIPART_CHUNKSIZE_IN_MB;
        if (!is_int($sizeInMB) || $sizeInMB < 5)
        {
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                . " Chunksize should be a valid integer greater or equal to 5 MB . Defaulting to 5");
            $sizeInMB = 5;
        }
        $sizeInBytes = (float)$sizeInMB * 1024*1024; // In bytes
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            . " Using multipart chunksize : " .$sizeInBytes);
        return $sizeInBytes;
    }
}