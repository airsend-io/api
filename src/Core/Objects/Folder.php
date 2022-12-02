<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Service\Storage\Shared\StorageObject;

class Folder extends FileSystemObject
{
    public function __construct()
    {

    }

    public function jsonSerialize()
    {
        $out = [
            'name' => $this->getName(),
            'displayname' => $this->getDisplayName(),
            'fullpath' => $this->getFullPath(),
            'displaypath' => $this->getDisplayPath(),
            'parent' => $this->getParent(),
            //'ext' => $this->getExtension(),
            'type' => 'folder',
            //'size' => $this->getSize(),
            'creation' => $this->getCreationDate(),
            'creationts' => !empty($this->getCreationDate()) ? strtotime($this->getCreationDate()) : null,
            'modification' => $this->getModificationDate(),
            'modificationts' => !empty($this->getModificationDate()) ? strtotime($this->getModificationDate()) : null,
            'access' => $this->getAccessDate(),
            'accessts' => !empty($this->getAccessDate()) ? strtotime($this->getAccessDate()) : null,
            //'versionidentifier' => $this->getVersionIdentifier(),
            //'syncversion' => $this->getSyncVersion(),
//            'by' => $this->getBy(),
            'candownload' => $this->canDownload(),
            'canmove' => $this->canMove(),
            'candelete' => $this->canDelete()
        ];

        if (sizeof($this->getFlags()) > 0)
            $out['flags'] =  $this->getFlags();
        return $out;
    }
}