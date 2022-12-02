<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Service\Storage\Util;

use CodeLathe\Service\Storage\Backstore\AbstractBackstore;
use CodeLathe\Service\Storage\Config\StorageServiceConfig;
use CodeLathe\Service\Storage\StorageService;


/**
 * Class StorageServiceUtility
 * @package CodeLathe\Service\Storage\Util
 * @deprecated
 */
class StorageServiceUtility
{

    /*
     * Function to check if the storage zone specified by the given id is valid
     */
    /**
     * @param $storageZoneId
     * @return bool
     */
    public static function isStorageBackendReady($storageZoneId): bool
    {
        $backstoreImpl = AbstractBackstore::getInstance($storageZoneId);
        return isset($backstoreImpl);
    }

    /**
     * @param $filename
     * @return bool
     */
    public static function isValidFileName($filename): bool
    {
        //check for empty string
        if (strlen($filename) == 0)
            return false;

        ///check if filename is dot or double dot
        if ($filename == ".." || $filename == ".")
        {
            return false;
        }

        //check if name has valid characters
        if (preg_match('/[^\PC\s]/u', $filename))
        {
            StorageService::logger()->debug(__FUNCTION__ . ' : ' .$filename . " name has invalid control characters, rejecting filename");
            return false;
        }

        if (StorageServiceConfig::TONIDO_DISABLE_DOTDOT)
        {
            if (stristr($filename, "..") !== FALSE)
                return false;
        }


        if (!StorageServiceConfig::TONIDO_DISABLE_INVALIDUTF8_UPLOAD) {
            $agent = StorageServiceUtility::parseUserAgent(StorageServiceUtility::getUserAgent());
            if ($agent != "Web browser") {
                if (extension_loaded("intl")) {
                    if (!normalizer_is_normalized($filename)) {
                        StorageService::logger()->debug(FCLogger::getCode([FCLogger::FILE_NAME_NOT_UTF8_NORMALIZED])." ".$filename . " name is not UTF normalized, make sure to verify all apps are up to date.");
                        return false;
                    }
                }
            }
        }

        if (preg_match('#[\\\\:*?"<>|/]#', $filename) || StorageServiceUtility::endsWith($filename,'.'))
            return false;
        else
            return true;
    }

    /**
     * @param $a_path
     * @param $a_ext
     * @param int $size
     * @return bool
     */
    public static function endsWith($a_path, $a_ext, $size = 0)
    {
        if ($size == 0)
            $size = strlen($a_ext);
        return $a_ext === "" || substr($a_path, -$size) === $a_ext;
    }


    /**
     * @param $parentPath
     * @param $childName
     * @return string
     */
    public static function convertToFullPath($parentPath, $childName)
    {
        return rtrim($parentPath, '/').'/' . $childName;
    }

    /**
     * @return string|string[]|null
     */
    public static function getUserAgent()
    {
        return preg_replace('/[[:^print:]]/', '', empty($_SERVER['HTTP_USER_AGENT'])?'Unknown':$_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * @param $useragent
     * @return string
     */
    public static function parseUserAgent($useragent){
        $agent = "";
        if ($useragent == "")
            return "Unknown";

        // ... The order of checking matters, so don't re-order them without
        // ... knowing what you are doing
        if (strpos($useragent,'Firefox') !== false)
            $agent = 'Web browser';
        else if (strpos($useragent,'Chrome') !== false)
            $agent = 'Web browser';
        else if (strpos($useragent,'Safari') !== false)
            $agent = 'Web browser';
        else if (strpos($useragent,'MSIE') !== false)
            $agent = 'Web browser';
        else if (strpos($useragent,'Mozilla') !== false)
            $agent = 'Web browser';
        else if (strpos(mb_strtolower($useragent),'android') !== false)
            $agent = 'Android';
        else if ( (strpos(mb_strtolower($useragent),'ios') !== false) ||
            (strpos(mb_strtolower($useragent),'iphone') !== false) ||
            (strpos(mb_strtolower($useragent), 'ipad') !== false))
            $agent = 'iOS';
        else if (strpos($useragent,'MS Outlook') !== false)
            $agent = 'MS Outlook';
        else if (strpos($useragent,'MS Office') !== false)
            $agent = 'MS Office';
        else
            $agent = substr($useragent,0,20);

        return $agent;
    }

    public static function parseWebBrowser($useragent){
        $agent = "";
        if ($useragent == "")
            return "Unknown";

        // ... The order of checking matters, so don't re-order them without
        // ... knowing what you are doing
        if (strpos($useragent,'Firefox') !== false)
            $agent = 'firefox';
        else if (strpos($useragent,'Edge') !== false)
            $agent = 'edge'; //ie-11 due to user agent changes
        else if (strpos($useragent,'Chrome') !== false)
            $agent = 'chrome';
        else if (strpos($useragent,'Safari') !== false)
            $agent = 'safari';
        else if (strpos($useragent,'MSIE') !== false)
            $agent = 'ie';
        else if (strpos($useragent,'Trident') !== false)
            $agent = 'ie'; //ie-11 due to user agent changes
        else if (strpos($useragent,'Mozilla') !== false)
            $agent = 'mozilla';
        else if (strpos(mb_strtolower($useragent),'android') !== false)
            $agent = 'android';
        else if ( (strpos(mb_strtolower($useragent),'ios') !== false) ||
            (strpos(mb_strtolower($useragent),'iphone') !== false) ||
            (strpos(mb_strtolower($useragent), 'ipad') !== false))
            $agent = 'ios';
        else if (strpos($useragent,'MS Outlook') !== false)
            $agent = 'outlook';
        else if (strpos($useragent,'MS Office') !== false)
            $agent = 'office';
        else
            $agent = substr($useragent,0,20);

        return $agent;
    }
    /**
     * @param $fileName
     * @return string
     */
    public static function getBrowserSpecificFileNameForDownload($fileName){
        $browser = self::parseWebBrowser(self::getUserAgent());
        if($browser == "ie" || $browser == "edge"){
            //IE needs url to be encoded or will cause filename codepage issues during download
            return  rawurlencode($fileName);
            //change + to space
        }
        else{
            return $fileName;
        }
    }

    /**
     * @param $filepath
     * @return array|null
     */
    public static function mb_pathinfo($filepath) : ?array
    {
        $m = array();
        preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im', $filepath, $m);
        $ret = array();
        if (isset($m[1]))
            $ret['dirname'] = $m[1];
        if (isset($m[2]))
            $ret['basename'] = $m[2];
        if (isset($m[5]))
            $ret['extension'] = $m[5];
        if (isset($m[3]))
            $ret['filename'] = $m[3];
        return $ret;
    }

    /**
     * @param $filepath
     * @return array
     */
    public static function getPathInfo($filepath)
    {
        $m = array();
        preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im', $filepath, $m);
        $ret = array();
        if (isset($m[1]))
            $ret['dirname'] = $m[1];
        if (isset($m[2]))
            $ret['basename'] = $m[2];
        if (isset($m[5]))
            $ret['extension'] = $m[5];
        if (isset($m[3]))
            $ret['filename'] = $m[3];
        return $ret;
    }

    /**
     * @param $filePath
     * @return mixed
     */
    public static function normalizedFileSystemPath($filePath)
    {
        $normalPath = str_replace("\\", "/", $filePath);
        return $normalPath;
    }

    /**
     * @param $inputPath
     * @param $parentpath
     * @param $name
     */
    public static function splitPaths($inputPath, &$parentpath, &$name)
    {
        //// ****************************************************************
        //// **** WARNING ****: ONLY FC PATHS will work, ie file system paths
        //// like C:\data\folder1 will not work!!
        //// ****************************************************************
        $inputPath = StorageServiceUtility::normalizedFileSystemPath($inputPath);
        $inputPath = rtrim(ltrim($inputPath, "/"),"/");
        $parentpath = $inputPath;
        if ($inputPath != "" && $inputPath != "/") {

            $folderPathArray = explode("/", $inputPath);
            $name = array_pop($folderPathArray);
            $parentpath = "";
            foreach ($folderPathArray as &$folder)
            {
                $parentpath = $parentpath . "/" . $folder;
            }

            if ($parentpath == "")
                $parentpath = "/";
        }
    }

    /**
     * @param string $inPath
     * @return array
     */
    public static function pathHierrarchysAsArray(string $inPath) : array
    {
        $pathHierrarchyArray = array();
        if(empty($inPath) || $inPath === "/"){
            return $pathHierrarchyArray;
        }
        $inPathComponentArray = explode("/", $inPath);
        $parentfullpath = "";
        foreach ($inPathComponentArray as &$folder)
        {
            if(empty($folder)) continue;

            $parentfullpath = $parentfullpath . "/" . $folder;
            $pathcomponent = ""; $namecomponent = "";
            StorageServiceUtility::splitPaths($parentfullpath, $pathcomponent, $namecomponent);
            $pathHierrarchyArray[] = array(
                "parentpath" => $pathcomponent,
                "name" => $namecomponent
            );
        }
        return $pathHierrarchyArray;
    }

    /**
     * @return string
     */
    public static function generateUniqueID() : string
    {
        return str_replace(".", "", \uniqid('', true));
    }

    /**
     * @return string
     * @throws \Exception
     */
    public static function getNowDateAsString() : string
    {
        $now = new \DateTime();
        return $now->format('Y-m-d H:i:s');
    }

    /**
     * @param $a_fileName
     * @return mixed|string
     */
    public static function getHTTPContentType($a_fileName) {
        $mime_types = array(
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',
            'xml' => 'text/xml',
            'woff' => 'application/x-font-woff',
            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',
            // audio/video
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'mkv' => 'video/mkv',
            'm4v' => 'video/m4v',
            'wmv' => 'video/x-ms-wmv',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'avi' => 'video/x-msvideo',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'ogg' => 'application/ogg',
            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            // ms office
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $exploded = explode('.', $a_fileName);
        $ext = \mb_strtolower(array_pop($exploded));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }

        return 'application/octet-stream';
    }

    /**
     * @return string|null
     */
    public static function getTempFolder() : ?string
    {
        
        $tempFolder =  StorageServiceConfig::CLSTORAGE_NODE_COMMON_TEMP_FOLDER;
        if(!empty($tempFolder)){
            if((substr($tempFolder, -1) == '/') || (substr($tempFolder, -1) == '\\')){
                $tempFolder = substr($tempFolder, 0, -1);
            }
        } else {
            $tempFolder = CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'scratch'.DIRECTORY_SEPARATOR.'tmp';
        }

        if (!file_exists($tempFolder) && !mkdir($tempFolder,0777,true)) {
            // Problem!
            StorageService::logger()->error(__FUNCTION__ . ' : ' .__FUNCTION__." Failed creating folder ".$tempFolder);
            return NULL;
        }
        //StorageService::logger()->debug("Temp folder = ".$tempFolder);
        return $tempFolder;
    }
}