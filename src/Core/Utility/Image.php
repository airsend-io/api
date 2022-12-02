<?php declare(strict_types=1);

/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\SecurityException;
use CodeLathe\Core\Utility\SafeFile;
use lsolesen\pel\Pel;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelJpeg;
use Psr\Log\LoggerInterface;


class Image
{
    /**
     *
     * Resize an image
     *
     * @param string $filePhyPath
     * @param string $fileExt
     * @param int $a_dstWidth
     * @param int $a_dstHeight
     * @param string $tgtPhyFile
     * @return bool
     * @throws SecurityException
     */
    public static function resizeImage(string $filePhyPath, string $fileExt, int $a_dstWidth, int $a_dstHeight, string $tgtPhyFile)
    {
        $logger = ContainerFacade::get(LoggerInterface::class);

        // TODO: Check max size and disallow resize for too large images

        $src_img = FALSE;
        if (SafeFile::file_exists($filePhyPath)) {
            try {
                switch (strtolower($fileExt)) {
                    case "jpg":
                    case "jpeg":
                        $src_img = \imagecreatefromjpeg($filePhyPath);

                        // save jpeg exif info from the original file
                        $pel = new PelJpeg($filePhyPath);
                        if ($pel instanceof PelJpeg) {
                            $originalExif = $pel->getExif();
                        }

                        break;
                    case "png":
                        $src_img = \imagecreatefrompng($filePhyPath);
                        break;
                    case "gif":
                        $src_img = \imagecreatefromgif($filePhyPath);
                        break;
                    default;
                        throw new NotImplementedException();
                }
            } catch (\Exception $ex) {

                $logger->debug(__CLASS__.":".__FUNCTION__. " Exception when Resizing Image ". $ex->getMessage());
                return false;
            }
        }

        if ($src_img === FALSE) {
            $logger->debug(__CLASS__.":".__FUNCTION__. " Bad src_img when resizing image ");
            return false;
        }

        //gets the dimmensions of the image
        $old_x = imageSX($src_img);
        $old_y = imageSY($src_img);

        $thumb_w = $old_x;
        $thumb_h = $old_y;

        if (($thumb_w > $a_dstWidth) || ($thumb_h > $a_dstHeight)) {
            $ratio1 = $old_x / $a_dstWidth;
            $ratio2 = $old_y / $a_dstHeight;
            if ($ratio1 > $ratio2) {
                $thumb_w = $a_dstWidth;
                $thumb_h = $old_y / $ratio1;
            } else {
                $thumb_h = $a_dstHeight;
                $thumb_w = $old_x / $ratio2;
            }
        }
        // we create a new image with the new dimmensions
        $dst_img = \imagecreatetruecolor((int)$thumb_w, (int)$thumb_h);
        $dst_img_size = $thumb_w * $thumb_h;

        if (!strcmp("png", $fileExt)) {
            \imagealphablending($dst_img, false); // setting alpha blending on
            \imagesavealpha($dst_img, true); // save alphablending setting (important)

            $transparent = \imagecolorallocatealpha($dst_img, 255, 255, 255, 255);
            if ($transparent == false)
                $transparent = 0;
            \imagefilledrectangle($dst_img, 0, 0, (int)$thumb_w, (int)$thumb_h, $transparent);

            // resize the big image to the new created one
            \imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, (int)$thumb_w, (int)$thumb_h, $old_x, $old_y);

            // Start with average quality
            $qual = 4;
            if (($thumb_w > 800 ) || $thumb_h > 800)
            {
                $qual = 2;
            }

            \imagepng($dst_img, $tgtPhyFile,$qual);
        } else {
            // resize the big image to the new created one
            \imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, (int)$thumb_w, (int)$thumb_h, $old_x, $old_y);

            // Start with average quality
            $qual = 70;
            if (($thumb_w > 800 ) || $thumb_h > 800)
            {
                $qual = 90;
            }
            \imagejpeg($dst_img, $tgtPhyFile, $qual);
        }
        //$dst_img_size = $this->getFileSize($tgtPhyFile);

        imagedestroy($dst_img);
        imagedestroy($src_img);

        // handle exif (if the original exif is set)
        if (isset($originalExif) && $originalExif instanceof PelExif) {
            $pel = new PelJpeg($tgtPhyFile);
            $pel->setExif($originalExif);
            $pel->saveFile($tgtPhyFile);
        }

        return true;
    }
}