<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Service\Storage\DB;


use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Service\Database\FSDatabaseService;
use CodeLathe\Service\Storage\Shared\FolderPropertiesObject;
use CodeLathe\Service\Storage\Shared\StorageObject;
use CodeLathe\Service\Storage\Shared\ListQueryObject;
use CodeLathe\Service\Storage\Util\StorageServiceUtility;
use CodeLathe\Service\Storage\StorageService;
use PDO;

/**
 * Class StorageServiceDB
 * @package CodeLathe\Service\Storage\DB
 */
class StorageServiceDB
{
    /** @var FSDatabaseService*/
    protected $dbService;

    /**
     * StorageServiceDB constructor.
     * @param FSDatabaseService $dbservice
     */
    public function __construct(FSDatabaseService $dbservice)
    {
        //store values
        $this->dbService = $dbservice;
    }

    /**
     * Function to add a new folder object into DB
     * @param StorageObject $folderObject
     * @return bool
     */
    public function addFolderObject(StorageObject $folderObject) : bool
    {
        
        $insertquery = "INSERT INTO items (
                            storagezoneid, name, extension, parentpath, type, size, owner, creationdate, 
                            modificationdate, fileid
                        )
                        VALUES (
                            :objectstoragezoneid,
                            :objectname,                            
                            :objectext,
                            :objectparentpath,
                            :objecttype,
                            :objectsize,
                            :objectowner,
                            :objectcreationdate,
                            :objectmodificationdate,
                            :objectfileid
                        )";
        try
        {
            $count = $this->dbService->insert($insertquery, $folderObject->getNewObjectArray());
            if($count > 0){
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Added folder to db ".$folderObject->getObjectFullPath() );
                $lastId = $this->dbService->lastInsertId();
                $folderObject->setObjectId($lastId);
                return TRUE;
            } else {
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to add folder to DB "
                    .$folderObject->getObjectFullPath() );
                return FALSE;
            }
        }
        catch (\PDOException $exception)
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
            return FALSE;
        }
    }

    /**
     * Function to add a new file or sidecarfile into the DB
     * @param StorageObject $fileObject
     * @param bool $deferredDeleteStore
     * @return bool
     */
    public function addFileObject(StorageObject $fileObject, bool $deferredDeleteStore = FALSE) : bool
    {
        
        $insertquery = "INSERT INTO ".($deferredDeleteStore?"deferreddeleteitems":"items")." (
                            storagezoneid, name, extension, parentpath, type, size, owner, 
                            creationdate, modificationdate,
                            complete, storagepath, fileid, backstoredata"
                            .($deferredDeleteStore?", deleteddate":"")
                            .($fileObject->isSideCarFile()?", sidecarmetadata":"")
                        .") VALUES (
                            :objectstoragezoneid,
                            :objectname,
                            :objectext,
                            :objectparentpath,
                            :objecttype,
                            :objectsize,
                            :objectowner,
                            :objectcreationdate,
                            :objectmodificationdate,
                            :objectcomplete,
                            :objectstoragepath,
                            :objectfileid,
                            :objectbackstoredata"
                            .($deferredDeleteStore?", :".StorageObject::OBJECT_DELETEDATE:"")
                            .($fileObject->isSideCarFile()?", :".StorageObject::OBJECT_SIDECARMETADATA:"")
                        .")";
        try
        {
            $type = $fileObject->getType();
            $insertValues = $fileObject->getNewObjectArray();
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Adding $type object to DB : "
            //    . $fileObject->getObjectFullPath());
            $count = $this->dbService->insert($insertquery,  $insertValues);
            if($count > 0) {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Added $type to DB " . $fileObject->getObjectFullPath() );
                $lastId = $this->dbService->lastInsertId();
                $fileObject->setObjectId($lastId);
                return TRUE;
            } else {
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to add $type to DB "
                    .$fileObject->getObjectFullPath() );
                return FALSE;
            }

        }
        catch (\PDOException $exception)
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
            return FALSE;
        }
    }

    /**
     * Function to update existing file in the DB
     * @param StorageObject $fileObject
     * @param bool $deferredDeleteStore
     * @return bool
     */
    public function updateFileObject(StorageObject $fileObject) : bool
    {

        $insertquery = "UPDATE items SET size = :objectsize, creationdate = :objectcreationdate, 
                        modificationdate = :objectmodificationdate, complete = :objectcomplete, 
                        backstoredata = :objectbackstoredata WHERE id = :objectid";
        try
        {
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Updating file object in DB : "
             //   . $fileObject->getObjectFullPath());
            $inputArray = array(
                ":objectsize" => $fileObject->getFileSize(),
                ":objectcreationdate" => $fileObject->getCreationDate(),
                ":objectmodificationdate" => $fileObject->getModificationDate(),
                ":objectcomplete" => $fileObject->isComplete(),
                ":objectbackstoredata" => $fileObject->getBackstoredataAsString(),
                ":objectid" => $fileObject->getObjectId(),
            );
            $count = $this->dbService->update($insertquery,  $inputArray);
            if($count > 0) {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Updated file in DB " . $fileObject->getObjectFullPath() );
                return TRUE;
            } else {
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                    .": Unable to update file in DB ".$fileObject->getObjectFullPath() );
                return FALSE;
            }

        }
        catch (\PDOException $exception)
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
            return FALSE;
        }
    }
    /**
     * Function to remove file or folder from DB
     * @param StorageObject $storageObject
     * @return bool
     */
    public function deleteStorageObject(StorageObject $storageObject) : bool
    {
        
        $deletequery = "DELETE FROM items WHERE id = :objectid";
        try
        {
            $count = $this->dbService->delete($deletequery, array(
                ":objectid" => $storageObject->getObjectId()
            ));
            if($count > 0) {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Removed storage item from DB : "
                //    .$storageObject->getObjectFullPath() );
                return TRUE;
            } else {
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to remove storage item ".
                    "from DB ".$storageObject->getObjectFullPath() );
                return FALSE;
            }

        }
        catch (\PDOException $exception)
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                .": Unable to remove storage item from DB : ".$exception->getMessage() );
            return FALSE;
        }
    }

    /**
     * Function to copy a storage item from main table to deferred delete table
     * @param StorageObject $storageObject
     * @return bool
     */
    public function copyFileObjectToDeferredDeleteStore(StorageObject $storageObject) : bool
    {
        return $this->addFileObject($storageObject, TRUE);
    }

    /**
     * Function returns a folder object for a given path
     * @param string $path
     * @return StorageObject|null
     */
    public function getFolderObjectForPath(string $path) : ?StorageObject
    {
        $parentpath = "";
        $name = "";
        StorageServiceUtility::splitPaths($path, $parentpath, $name);
        return $this->getFolderObjectForParentPathAndName($parentpath, $name);
    }

    /**
     * @param string $parentpath
     * @param string $name
     * @return StorageObject|null
     */
    public function getFolderObjectForParentPathAndName(string $parentpath, string $name) : ?StorageObject
    {
        

        $foundObject = NULL;
        $selectquery = "SELECT * FROM items WHERE parentpath = :objectparentpath AND name = :objectname";

        try
        {

            $foundObject = $this->dbService->selectObject($selectquery, StorageObject::class,
                array(
                ":objectparentpath" => $parentpath,
                ":objectname" => $name
            ));
            if($foundObject){
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Found item matching "
                //    .StorageServiceUtility::convertToFullPath( $parentpath, $name));
            }
            else{
                $foundObject = NULL;
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": No match found "
                //    .StorageServiceUtility::convertToFullPath( $parentpath, $name));
            }

        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return $foundObject;
    }

    /**
     * @param ListQueryObject $queryObject
     * @return iterable
     */
    public function getLiveChildObjectsForPath(ListQueryObject $queryObject, &$count = null) : iterable
    {
        $foundObject = NULL;
        $selectStr = "SELECT * FROM items ";
        $wherestr = "WHERE (parentpath = :topparentpath OR parentpath LIKE :allchildpaths) " .
            "AND type IN ('file', 'folder') " .
            "AND (type = 'folder' OR (type = 'file' AND versioneddate IS NULL AND complete = 1)) ";

        if($queryObject->leafFirst()){
            $orderstr = "ORDER BY type ASC, modificationdate DESC "; // leaf files first, newer files first
        } else {
            $orderstr = "ORDER BY type DESC, modificationdate DESC "; // folders first, newer files first
        }

        $limitstr = "";
        if($queryObject->getLimit() != -1){
            $limitstr = "LIMIT ".$queryObject->getOffset().", ".$queryObject->getLimit();
        }

        try
        {
            $selectquery = $selectStr . $wherestr . $orderstr . $limitstr;
            $parentpath = $queryObject->getParentPath();
            $allchildpaths = $queryObject->doRecursive()?"$parentpath/%" : "";
            $iterable = $this->dbService->selectObjects($selectquery, StorageObject::class,
                array(":topparentpath" => $parentpath,
                    ":allchildpaths" => $allchildpaths));
            foreach ($iterable as $foundObject){
                yield $foundObject;
            }

            //check count is requested
            if(isset($count)){
                //form count query
                $countselector = "COUNT(*)";
                $countstr = "SELECT $countselector FROM items ";
                $countquery = $countstr . $wherestr;
                $result = $this->dbService->selectOne($countquery,  array(
                    ":topparentpath" => $parentpath,
                    ":allchildpaths" => $allchildpaths));
                if(isset($result[$countselector])){
                    $count = (int) $result[$countselector];
                } else{
                    $count = -1;
                }
            }
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
            yield NULL;
        }
    }

    /**
     * @param string $parentpath
     * @param string $name
     * @param bool $caseInsensitive
     * @return StorageObject|null
     */
    public function getLiveStorageObjectForParentPathAndName(string $parentpath, string $name,
                                                             bool $caseInsensitive = FALSE) : ?StorageObject
    {
        $foundObject = NULL;
        $selectquery = "SELECT * FROM items WHERE parentpath LIKE BINARY :objectparentpath 
                        AND name LIKE ".($caseInsensitive?"":"BINARY")." :objectname AND (type = 'folder' OR 
                        (type = 'file' AND versioneddate IS NULL AND complete = 1))";
        try
        {
            $foundObject = $this->dbService->selectObject($selectquery, StorageObject::class,
                array(
                ":objectparentpath" => $parentpath,
                ":objectname" => $name
            ));
            if($foundObject instanceof StorageObject){
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Found item matching "
                //    .StorageServiceUtility::convertToFullPath( $parentpath, $name));
            }
            else{
                $foundObject = NULL;
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": No match found "
                //    .StorageServiceUtility::convertToFullPath( $parentpath, $name));
            }

        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return $foundObject;
    }

    /**
     * @param string $parentpath
     * @param string $name
     * @return array
     */
    public function getLiveAndPartialFileObjectForParentPathAndName(string $parentpath, string $name) : array
    {

        $liveObject = null; $partialObject = null;
        $selectquery = "SELECT * FROM items WHERE parentpath = :objectparentpath 
                        AND name = :objectname 
                        AND type = 'file'";
        /**@var StorageObject $storageObject */
        $storageObject = null;
        try
        {
            $iterable = $this->dbService->selectObjects($selectquery, StorageObject::class,
                array(
                    ":objectparentpath" => $parentpath,
                    ":objectname" => $name
                ));
            foreach($iterable as $storageObject){
                if($storageObject->isLiveVersion())
                {
                    //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                    //    .": Found live storage item for ".$storageObject->getObjectFullPath());
                    $liveObject = $storageObject;
                }
                elseif ($storageObject->isPartialVersion())
                {
                    //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                     //   .": Found partial storage item for ".$storageObject->getObjectFullPath());
                    $partialObject = $storageObject;
                }
            }

        }
        catch (\PDOException $exception)
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return array ($liveObject, $partialObject);
    }

    /**
     * @param StorageObject $fileObject
     * @return array
     */
    public function getNonLiveFileObjects(StorageObject $fileObject) : array
    {
        $foundObjects = array();
        $selectquery = "SELECT * FROM items WHERE parentpath = :objectparentpath 
                        AND name = :objectname AND versioneddate IS NOT NULL AND complete = 1 AND type = 'file'
                        ORDER BY versioneddate ASC";

        try
        {
            $iterable = $this->dbService->selectObjects($selectquery, StorageObject::class,
                array(
                    ":objectparentpath" => $fileObject->getParentPath(),
                    ":objectname" => $fileObject->getName()
                ));
            foreach ($iterable as $foundObject){
                $foundObjects[] = $foundObject;
            }
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return $foundObjects;
    }

    /**
     * @param StorageObject $fileObject
     * @return array
     */
    public function getNonLiveFileObjectByID(StorageObject $fileObject) : ?StorageObject
    {
        $selectquery = "SELECT * FROM items WHERE parentpath = :objectparentpath 
                    AND name = :objectname AND versioneddate IS NOT NULL AND versioneddate = :objectverid 
                    AND complete = 1 AND type = 'file'";

        try
        {
            $foundObject = $this->dbService->selectObject($selectquery, StorageObject::class,
                array(
                    ":objectparentpath" => $fileObject->getParentPath(),
                    ":objectname" => $fileObject->getName(),
                    ":objectverid" => $fileObject->getObjectVersionIdentifier()
                ));
            return $foundObject;

        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return NULL;
    }

    /**
     * @param StorageObject $fileObject
     * @return array
     */
    public function getSideCarFileObject(StorageObject $fileObject) : ?StorageObject
    {

        $selectquery = "SELECT * FROM items WHERE parentpath = :objectparentpath 
                        AND name = :objectname AND type = '".StorageObject::OBJECTTYPE_SIDECARFILE
                        . "' AND sidecarmetadata = :objectsidecarmetadata";

        try
        {
            $foundObject = $this->dbService->selectObject($selectquery, StorageObject::class,
                array(
                    ":objectparentpath" => $fileObject->getParentPath(),
                    ":objectname" => $fileObject->getName(),
                    ":objectsidecarmetadata" => $fileObject->getSidecarMetadataAsString()
                ));
            if($foundObject){
                return $foundObject;
            }
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return NULL;
    }

    /**
     * @param StorageObject $fileObject
     * @return array
     */
    public function getAllSideCarFileObjects(StorageObject $fileObject) : array
    {
        $foundObjects = array();
        $selectquery = "SELECT * FROM items WHERE parentpath = :objectparentpath 
                        AND name = :objectname AND type = '".StorageObject::OBJECTTYPE_SIDECARFILE . "'";
        try
        {
            $iterable = $this->dbService->selectObjects($selectquery, StorageObject::class,
                array(
                    ":objectparentpath" => $fileObject->getParentPath(),
                    ":objectname" => $fileObject->getName()
                ));
            foreach ($iterable as $foundObject){
                $foundObjects[] = $foundObject;
            }
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return $foundObjects;
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    public function deleteNonLiveFileObjects(StorageObject $fileObject) : bool
    {
        
        $deletequery = "DELETE FROM items WHERE parentpath = :objectparentpath 
                        AND name = :objectname AND versioneddate IS NOT NULL AND complete = 1";

        try
        {
            $count = $this->dbService->delete($deletequery, array(
                ":objectparentpath" => $fileObject->getParentPath(),
                ":objectname" => $fileObject->getName()
            ));
            if($count > 0) {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Removed non-live file items from DB : ".$fileObject->getObjectFullPath() );
                return TRUE;
            } else {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Failed to remove non-live file items from DB : ".$fileObject->getObjectFullPath() );
                return FALSE;
            }
        }
        catch (\PDOException $exception)
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                .": Unable to non-live remove file items from DB : ".$exception->getMessage() );
            return FALSE;
        }
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    public function deleteSideCarFileObject(StorageObject $fileObject) : bool
    {

        $deletequery = "DELETE FROM items WHERE parentpath = :objectparentpath 
                        AND name = :objectname AND  type = '".StorageObject::OBJECTTYPE_SIDECARFILE
                    . "' AND sidecarmetadata = :objectsidecarmetadata";

        try
        {
            $count = $this->dbService->delete($deletequery, array(
                ":objectparentpath" => $fileObject->getParentPath(),
                ":objectname" => $fileObject->getName(),
                ":objectsidecarmetadata" => $fileObject->getSidecarMetadataAsString()
            ));
            if($count > 0) {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Removed sidecar file items from DB : ".$fileObject->getObjectFullPath() );
                return TRUE;
            } else {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                 //   .": Failed to remove sidecar file items from DB : ".$fileObject->getObjectFullPath() );
                return FALSE;
            }
        }
        catch (\PDOException $exception)
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                .": Unable to sidecar remove file items from DB : ".$exception->getMessage() );
            return FALSE;
        }
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    public function deleteAllSideCarFileObjects(StorageObject $fileObject) : bool
    {

        $deletequery = "DELETE FROM items WHERE parentpath = :objectparentpath 
                        AND name = :objectname AND  type = '".StorageObject::OBJECTTYPE_SIDECARFILE . "'";

        try
        {
            $count = $this->dbService->delete($deletequery, array(
                ":objectparentpath" => $fileObject->getParentPath(),
                ":objectname" => $fileObject->getName()
            ));
            if($count > 0) {
                //removed all sidecar files. success
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Removed sidecar file items from DB : ".$fileObject->getObjectFullPath() );
            } else {
                //no sidecar files case. success as well
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                 //   .": No sidecar files to remove from DB : ".$fileObject->getObjectFullPath() );
            }
            return TRUE;
        }
        catch (\PDOException $exception)
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                .": Unable to sidecar remove file items from DB : ".$exception->getMessage() );
            return FALSE;
        }
    }


    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    public function moveLiveToNonLiveFileObject(StorageObject $fileObject) : bool
    {
        $updatequery = "UPDATE items SET versioneddate = :objectversioneddate WHERE id = :objectid";

        try
        {
            $count = $this->dbService->update($updatequery, array(
                ":objectid" => $fileObject->getObjectId(),
                ":objectversioneddate" => StorageServiceUtility::getNowDateAsString()
            ));
            //not doing anything with count
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Updated live item to be a old version in db "
            //    .$fileObject->getObjectFullPath() );
            return TRUE;
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return FALSE;
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    public function updateLastAccessDate(StorageObject $fileObject) : bool
    {
        $updatequery = "UPDATE items SET lastaccessdate = :objectlastaccessdate WHERE id = :objectid";

        try
        {
            $count = $this->dbService->update($updatequery, array(
                ":objectid" => $fileObject->getObjectId(),
                ":objectlastaccessdate" => StorageServiceUtility::getNowDateAsString()
            ));
            //not doing anything with count
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            //    .": Updated last access date of file ".$fileObject->getObjectFullPath() );
            return TRUE;
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return FALSE;
    }

    /**
     * @param StorageObject $srcObject
     * @param StorageObject $tgtObject
     * @return bool
     */
    public function moveFileObject(StorageObject $srcObject, StorageObject $tgtObject) : bool
    {
        $updatequery = "UPDATE items SET parentpath = :objectparentpath, name = :objectname,
                        extension = :objectextension, owner = :objectowner, fileid = :objectnewfileid
                        WHERE fileid = :objectfileid";

        try
        {
            $count = $this->dbService->update($updatequery, array(
                ":objectfileid" => $srcObject->getFileId(),
                ":objectparentpath" => $tgtObject->getParentPath(),
                ":objectname" => $tgtObject->getName(),
                ":objectextension" => $tgtObject->getExtension(),
                ":objectowner" => $tgtObject->getOwner(),
                ":objectnewfileid" => $tgtObject->getFileId(),
            ));
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Moved file ".$srcObject->getObjectFullPath()
            //    . "->" . $tgtObject->getObjectFullPath());
            return TRUE;
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return FALSE;
    }

    /**
     * Sync related update functions
     */
    public function updateSyncVersionOnAdd(StorageObject $storageObject) : bool
    {
        

        $updatequery = "UPDATE items SET syncversion = :objectsyncversion";

        //narrow syncupdates only to file and folder (no sidecar files)
        $updatequery .= " WHERE type IN ('file', 'folder') AND ( ";

        //clause for object itself
        $updatequery .= "id = :objectid";

        //this could be a folder with files.. so add clause to update all of its subfolders and files
        if($storageObject->isFolder()) {
            $updatequery .= " OR (parentpath = :topparentpath OR parentpath LIKE :allchildpaths)";
        }

        //add path hierrarchy
        $idx=0;
        $parentHierrarchy = StorageServiceUtility::pathHierrarchysAsArray($storageObject->getParentPath());
        foreach ($parentHierrarchy as $parent){
            $updatequery .= " OR (type = 'folder' AND parentpath = :parentpath$idx AND name = :name$idx )";
            $idx++;
        }

        //update query complete
        $updatequery .= " )";

        //set values array
        $idx=0;
        $syncversion = sprintf("%.0f", (microtime(TRUE) * 1000 * 1000));
        $inputArray = array(
            ":objectsyncversion" => $syncversion,
            ":objectid" => $storageObject->getObjectId(),
        );
        if($storageObject->isFolder()){
            $inputArray[":topparentpath"] = $storageObject->getObjectFullPath();
            $inputArray[":allchildpaths"] = $storageObject->getObjectFullPath()."/%";
        }

        //add all parent paths to the update list
        foreach ($parentHierrarchy as $parent){
            $inputArray[":parentpath$idx"] = $parent['parentpath'];
            $inputArray[":name$idx"] = $parent['name'];
            $idx++;
        }

        //execute the update
        try{
            $count = $this->dbService->update($updatequery, $inputArray);
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Update sync version for folder :"
            //    . $storageObject->getObjectFullPath());
            return TRUE;
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return FALSE;
    }

    /**
     * @param StorageObject $storageObject
     * @return bool
     */
    public function updateSyncVersionOnDelete(StorageObject $storageObject) : bool
    {
        $updatequery = "UPDATE items SET syncversion = :objectsyncversion";

        //narrow sync updates only to files/folders (not sidecar files)
        $updatequery .= " WHERE type IN ('file', 'folder') AND ( ";
        $updatequery .= "1 = 2"; //dummy clause

        //add path hierrarchy
        $idx=0;
        $parentHierrarchy = StorageServiceUtility::pathHierrarchysAsArray($storageObject->getParentPath());
        foreach ($parentHierrarchy as $parent){
            $updatequery .= " OR (type = 'folder' AND parentpath = :parentpath$idx AND name = :name$idx )";
            $idx++;
        }

        //update query complete
        $updatequery .= " )";

        //set values array
        $idx=0;
        $syncversion = sprintf("%.0f", (microtime(TRUE) * 1000 * 1000));
        $inputArray = array(
            ":objectsyncversion" => $syncversion
        );

        //add all parent paths to the update list
        foreach ($parentHierrarchy as $parent){
            $inputArray[":parentpath$idx"] = $parent['parentpath'];
            $inputArray[":name$idx"] = $parent['name'];
            $idx++;
        }

        //execute the update
        try{
            $count = $this->dbService->update($updatequery, $inputArray);
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Update sync version for path hierrarchy :"
            //    . $storageObject->getObjectFullPath());
            return TRUE;
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return FALSE;
    }

    /**
     * Function to add new item metatags
     * @param StorageObject $fileObject
     * @param bool $deferredDeleteStore
     * @return bool
     */
    public function addObjectMetaTags(StorageObject $fileObject) : bool
    {

        $insertquery = "INSERT INTO itemmetatags (
                            fileid, metatags) VALUES (
                            :objectfileid,
                            :objectmetatags)";
        try
        {
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Adding new metatags to object : "
             //   . $fileObject->getObjectFullPath());
            $count = $this->dbService->insert($insertquery,  array(
                ":objectfileid" => $fileObject->getFileId(),
                ":objectmetatags" => $fileObject->getMetaTagsAsString()
            ));
            if($count > 0) {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Added new metatags to object " . $fileObject->getObjectFullPath() );
                $lastId = $this->dbService->lastInsertId();
                $fileObject->setObjectId($lastId);
                return TRUE;
            } else {
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to add metatags to object "
                    .$fileObject->getObjectFullPath() );
                return FALSE;
            }

        }
        catch (\PDOException $exception)
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
            return FALSE;
        }
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    public function updateObjectMetaTags(StorageObject $fileObject) : bool
    {
        $updatequery = "UPDATE itemmetatags SET metatags = :objectmetatags
                        WHERE fileid = :objectfileid";

        try
        {
            $count = $this->dbService->update($updatequery, array(
                ":objectfileid" => $fileObject->getFileId(),
                ":objectmetatags" => $fileObject->getMetaTagsAsString()
            ));
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            //    .": Updated object metatag ".$fileObject->getObjectFullPath());
            return TRUE;
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return FALSE;
    }

    /**
     * @param StorageObject $fileObject
     * @return array
     */
    public function getMetaTagsForObject(StorageObject $fileObject) : bool
    {

        $selectquery = "SELECT * FROM itemmetatags WHERE fileid = :objectfileid";

        try
        {
            $record = $this->dbService->selectOne($selectquery, array(
                    ":objectfileid" => $fileObject->getFileId()
                ));
            if(isset($record) && isset($record['metatags'])){
                $fileObject->setMetaTagsAsString($record['metatags']);
                return TRUE;
            }
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }
        return FALSE;
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    public function deleteObjectMetaTags(StorageObject $fileObject) : bool
    {

        $deletequery = "DELETE FROM itemmetatags WHERE fileid = :objectfileid";

        try
        {
            $count = $this->dbService->delete($deletequery, array(
                ":objectfileid" => $fileObject->getFileId()
            ));
            if($count > 0) {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Removed metatags from object : ".$fileObject->getObjectFullPath() );
                return TRUE;
            } else {
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                //    .": Failed to remove metatags from object : ".$fileObject->getObjectFullPath() );
                return FALSE;
            }
        }
        catch (\PDOException $exception)
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                .": Unable to remove metatags from object : ".$exception->getMessage() );
            return FALSE;
        }
    }

    /**
     * Sync related update functions
     */
    public function getFolderProperties(StorageObject $storageObject) : FolderPropertiesObject
    {
        $folderPropertiesObject = new FolderPropertiesObject();

        try{
            //live file count & size
            $liveFileQuery = "SELECT COUNT(*) AS COUNT, SUM(size) AS SIZE FROM items "
                ." WHERE (parentpath = :topparentpath OR parentpath LIKE :allchildpaths) AND type = 'file'"
                ." AND versioneddate IS NULL AND complete = 1";
            $result1 = $this->dbService->selectOne($liveFileQuery, array(
                ":topparentpath" => $storageObject->getObjectFullPath(),
                ":allchildpaths" => $storageObject->getObjectFullPath()."/%"
            ));
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Live file(s) result :"
            //    . $storageObject->getObjectFullPath(). "->" . print_r($result1, TRUE));
            if(isset($result1['COUNT'])){
                $folderPropertiesObject->setTotalLiveFileCount((int) $result1['COUNT']);
            }
            if(isset($result1['SIZE'])){
                $folderPropertiesObject->setTotalLiveFileSize((int) $result1['SIZE']);
            }

            //non live file count & size
            $nonLiveFileQuery = "SELECT COUNT(*) AS COUNT, SUM(size) AS SIZE FROM items "
                ." WHERE (parentpath = :topparentpath OR parentpath LIKE :allchildpaths) AND type = 'file'"
                ." AND versioneddate IS NOT NULL AND complete = 1";
            $result2 = $this->dbService->selectOne($nonLiveFileQuery, array(
                ":topparentpath" => $storageObject->getObjectFullPath(),
                ":allchildpaths" => $storageObject->getObjectFullPath()."/%"
            ));
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Non Live file(s) result :"
            //    . $storageObject->getObjectFullPath(). "->" . print_r($result2, TRUE));
            if(isset($result2['COUNT'])){
                $folderPropertiesObject->setTotalNonLiveFileCount((int) $result2['COUNT']);
            }
            if(isset($result2['SIZE'])){
                $folderPropertiesObject->setTotalNonLiveFileSize((int) $result2['SIZE']);
            }

            //sidecar file count & size
            $sidecarFileQuery = "SELECT COUNT(*) AS COUNT, SUM(size) AS SIZE FROM items "
                ." WHERE (parentpath = :topparentpath OR parentpath LIKE :allchildpaths) AND type = 'sidecarfile'";
            $result3 = $this->dbService->selectOne($sidecarFileQuery, array(
                ":topparentpath" => $storageObject->getObjectFullPath(),
                ":allchildpaths" => $storageObject->getObjectFullPath()."/%"
            ));
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Sidecar file(s) result :"
            //    . $storageObject->getObjectFullPath(). "->" . print_r($result3, TRUE));
            if(isset($result3['COUNT'])){
                $folderPropertiesObject->setTotalSidecarFileCount((int) $result3['COUNT']);
            }
            if(isset($result3['SIZE'])){
                $folderPropertiesObject->setTotalSidecarFileSize((int) $result3['SIZE']);
            }

            //all file count & size
            $allFileQuery = "SELECT COUNT(*) AS COUNT, SUM(size) AS SIZE FROM items "
                ." WHERE (parentpath = :topparentpath OR parentpath LIKE :allchildpaths) AND type <> 'folder'" ;
            $result4 = $this->dbService->selectOne($allFileQuery, array(
                ":topparentpath" => $storageObject->getObjectFullPath(),
                ":allchildpaths" => $storageObject->getObjectFullPath()."/%"
            ));
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": All file(s) result :"
            //    . $storageObject->getObjectFullPath(). "->" . print_r($result4, TRUE));
            if(isset($result4['COUNT'])){
                $folderPropertiesObject->setTotalFileCount((int) $result4['COUNT']);
            }
            if(isset($result4['SIZE'])){
                $folderPropertiesObject->setTotalFileSize((int) $result4['SIZE']);
            }

            //folder
            $folderQuery = "SELECT COUNT(*) AS COUNT FROM items "
                ." WHERE (parentpath = :topparentpath OR parentpath LIKE :allchildpaths) AND type = 'folder'" ;
            $result5 = $this->dbService->selectOne($folderQuery, array(
                ":topparentpath" => $storageObject->getObjectFullPath(),
                ":allchildpaths" => $storageObject->getObjectFullPath()."/%"
            ));
            //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Folder result :"
            //    . $storageObject->getObjectFullPath(). "->" . print_r($result5, TRUE));
            if(isset($result5['COUNT'])){
                $folderPropertiesObject->setTotalFolderCount((int) $result5['COUNT']);
            }
        }
        catch (\PDOException $exception){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$exception->getMessage() );
        }

        return $folderPropertiesObject;




    }

    /**
     * @param int|null $newSeconds number of seconds-old to consider a file "new". If null, the new info is not returned.
     * @return array|null
     * @throws DatabaseException
     */
    public function getDashBoardStats(?int $newSeconds = null)
    {
        $totalsQuery = "SELECT count(true) as total_count, sum(size) as total_size FROM items WHERE type = 'file' AND versioneddate is null;";
        try {
            $output = $this->dbService->selectOne($totalsQuery);
        } catch (\PDOException $e) {
            throw new DatabaseException($e->getMessage());
        }

        if ($newSeconds !== null) {
            $newQuery = "SELECT count(true) as new_count, sum(size) as new_size FROM items WHERE type = 'file' AND versioneddate is null AND creationdate > DATE_SUB(NOW(), INTERVAL :seconds SECOND);";
            try {
                $result = $this->dbService->selectOne($newQuery, ['seconds' => $newSeconds]);
                $output = array_merge($output, $result);
            } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage());
            }
        }

        return $output;
    }

}