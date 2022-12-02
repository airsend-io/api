<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

class File extends FileSystemObject
{
    public function __construct()
    {

    }

    /**
     * @return array|mixed
     * @deprecated Must be implemented on the child class
     */
    public function jsonSerialize()
    {
        $out = [
            'name' => $this->getName(),
            'displayname' => $this->getDisplayName(),
            'fullpath' => $this->getFullPath(),
            'displaypath' => $this->getDisplayPath(),
            'parent' => $this->getParent(),
            'ext' => $this->getExtension(),
            'type' => $this->getType(),
            'size' => $this->getSize(),
            'creation' => $this->getCreationDate(),
            'creationts' => strtotime($this->getCreationDate()),
            'modification' => $this->getModificationDate(),
            'modificationts' => strtotime($this->getModificationDate()),
            'access' => $this->getAccessDate(),
            'accessts' => ($this->getAccessDate() != "") ? strtotime($this->getAccessDate()) : "",
            'versionidentifier' => $this->getVersionIdentifier(),
//            'syncversion' => $this->getSyncVersion(),
            'by' => $this->getBy(),
            'candownload' => $this->canDownload(),
            'canmove' => $this->canMove(),
            'candelete' => $this->canDelete(),
        ];

        if (sizeof($this->getFlags()) > 0)
            $out['flags'] =  $this->getFlags();
        return $out;
    }

}