<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

/**
 * Manipulates Paths /A/B/C/Folder/name
 *
 * Minimum path is /A where / is the folder and A is the name
 * name can never be empty
 * miniumum path is /
 *
 * @deprecated
 */
class Path
{
    private $m_folder;
    private $m_name;
    private $m_normalized;


    public static function createFromPath($a_fullPath, $normalize = false)
    {
        $instance = new self();
        $instance->loadFromPath($a_fullPath, $normalize);
        return $instance;
    }

    private function loadFromParts($a_folder, $a_name, $normalize = false)
    {
        $this->m_folder = $a_folder;
        $this->m_name = $a_name;
        $this->m_normalized = false;

        if ($normalize) {
            $this->normalize();
        }
    }

    private function loadFromPath($a_fullPath, $normalize = false)
    {
        $a_folder = "";
        $a_name = "";
        $this->splitPaths($a_fullPath, $a_folder, $a_name);
        $this->m_folder = $a_folder;
        $this->m_name = $a_name;
        $this->m_normalized = false;

        if ($normalize) {
            $this->normalize();
        }
    }

    public function isValid()
    {
        $this->normalize();

        // ... Add more checks here
        if (strlen(trim($this->m_folder)) == 0) {
            return false;
        }

        return true;
    }

    public function isValidOwnerPath($a_owner)
    {
        $this->normalize();
        if ($this->m_folder == '/' && $this->m_name == '') {
            return true;
        }

        if ($this->getOwner() == $a_owner) {
            return true;
        }

        return false;
    }

    private function splitPaths($a_inputPath, &$a_path, &$a_name)
    {
        $a_inputPath = rtrim(ltrim($a_inputPath, "/"), "/");
        $a_path = $a_inputPath;
        $a_name = "";
        if ($a_inputPath != "" && $a_inputPath != "/") {
            $a_folderPathArray = explode("/", $a_inputPath);
            $a_name = array_pop($a_folderPathArray);
            $a_path = "";
            foreach ($a_folderPathArray as &$a_folder) {
                if (strlen(trim($a_folder)) > 0) {
                    $a_path = $a_path . "/" . $a_folder;
                }
            }

            if ($a_path == "") {
                $a_path = "/";
            }
        }
    }

    public function normalize()
    {
        if ($this->m_normalized) {
            return;
        }

        $this->m_folder = rtrim(ltrim($this->m_folder, "/"), "/");
        if ($this->m_folder == "") {
            $this->m_folder = "/";
        } else {
            $path = "";
            $name = "";
            $this->splitPaths($this->m_folder, $path, $name);
            if ($path == "/") {
                $this->m_folder = $path . $name;
            } else {
                $this->m_folder = $path . '/' . $name;
            }
        }

        $this->m_name = rtrim(ltrim($this->m_name, "/"), "/");
        $a_nameArray = explode("/", $this->m_name);
        if (count($a_nameArray) > 1) {
            throw new \Exception("Bad Name in Path");
        }

        $this->m_normalized = true;

    }

    public function getFullPath()
    {
        $this->normalize();
        if ($this->m_folder == "/") {
            return $this->m_folder . $this->m_name;
        }
        return $this->m_folder . "/" . $this->m_name;
    }

    public function getOwner()
    {
        $this->normalize();

        $trimPath = rtrim(ltrim($this->getFullPath(), "/"), "/");
        $fpathArray = explode("/", $trimPath);
        if (count($fpathArray) == 0) {
            throw new \Exception("No Owner in Path");
        }

        return $fpathArray[0];
    }

    public function getParent()
    {
        return $this->m_folder;
    }

    public function getName()
    {
        return $this->m_name;
    }

    /**
     * Given a path like /A/B/C, this will return /A, /A/B, /A/B/C
     *
     * @return string arrays of parent paths
     * @global type $g_log
     */
    public function getAllParentPaths()
    {
        return Path::getAllPaths($this->getFullPath());
    }

    public static function containsInvalidPath($path)
    {
        $trimpath = trim($path, "/");
        $explpaths = explode('/', $trimpath);
        foreach ($explpaths as $component) {
                if ($component == ".") {
                    return true;
                }

                if ($component == "..") {
                    return true;
                }
        }

        return false;
    }

    public static function getAllPaths($path)
    {
        $a_fullPath = $path;
        $a_inputPath = rtrim(ltrim($a_fullPath, "/"), "/");
        $a_folderPathArray = explode("/", $a_inputPath);
        $parentPaths = array();
        $cumPath = "/";
        foreach ($a_folderPathArray as $a_folder) {
            $cumPath .= $a_folder;
            $parentPaths[] = $cumPath;
            $cumPath .= "/";
        }

        return $parentPaths;
    }

    public static function mb_pathinfo(string $filepath)
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


}


