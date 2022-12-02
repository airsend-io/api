<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Service\Storage\Shared\FolderPropertiesObject;

class FolderProps  implements \JsonSerializable, ObjectInterface
{
    protected $data;
    protected $object;

    public function __construct()
    {
        $this->data['total_fs_count'] = 0;
        $this->data['total_file_count'] = 0;
        $this->data['total_folder_count'] = 0;
        $this->data['total_fs_size'] = 0;
        $this->object = null;
    }

    public function loadFromStorage(FolderPropertiesObject $object)
    {
        $this->data['total_fs_count'] = $object->getTotalLiveFileCount() + $object->getTotalFolderCount();
        $this->data['total_file_count'] = $object->getTotalLiveFileCount();
        $this->data['total_folder_count'] = $object->getTotalFolderCount();
        $this->data['total_fs_size'] = $object->getTotalLiveFileSize();

        $this->object = $object;
    }

    static public function withStorageObject(FolderPropertiesObject $object)
    {
        $folderProps = new FolderProps();
        $folderProps->loadFromStorage($object);
        return $folderProps;
    }

    public function getArray(): array
    {
        return $this->data;
    }

    public function setArray($data): void
    {
        $this->data = $data;
        $this->object = null;

    }


    public function jsonSerialize() : array
    {
        return $this->data;
    }

    public function getTotalSize()
    {
        return $this->data['total_fs_size'];
    }

    public function getTotalFiles()
    {
        return $this->data['total_file_count'];
    }


}