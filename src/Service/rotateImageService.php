<?php

namespace UploadImages\Service;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Session\Container;

class rotateImageService implements rotateImageServiceInterface {

    /**
     * @var \Blog\Service\PostServiceInterface
     */
    protected $config;
    protected $em;

    public function __construct($em, $config) {
        $this->config = $config;
        $this->em = $em;
    }

    public function createContainerImage($rotateImage = NULL, $rotateImages = NULL, $returnURL = NULL) {
        $container = new Container('rotateImage');
        $container->rotateImage = $rotateImage;
        $container->rotateImages = $rotateImages;
        $container->returnUrl = $returnURL;

        return true;
    }

    public function rotateImage($srcFile = null, $dstFile = null, $rotation = 0, $img_quality = 90) {
        
        // Check if file exist
        if (!file_exists($srcFile)) {
            return 'Could not find the original image';
        }

        // Check if the destionfolder is set. When false than Original location becomes Destination folder
        if ($dstFile == null) {
            $dstFile = dirname($srcFile);
        }

        // Check if the destination folder exist
        if (!is_dir($dstFile)) {
            return 'Folder ' . $dstFile . ' does not exist';
        }

        // Check if the directory has the appropiate rights
        if (substr(sprintf('%o', fileperms($dstFile)), -4) <> '0777') {
            return 'The folder does not has the appropriate rights to upload files.';
        }

        //get file info like basename and mime type of the file
        $sPathParts = pathinfo($srcFile);
        $sFileName = $sPathParts['basename'];
        $sMimeType = mime_content_type($srcFile);

        // Depending on wich file type is uploaded create a image
        if ($sMimeType == "image/jpeg") {
            $oSourceImage = imagecreatefromjpeg($srcFile);
        } else if ($sMimeType == "image/png") {
            $oSourceImage = imagecreatefrompng($srcFile);
        } else if ($sMimeType == "image/gif") {
            $oSourceImage = imagecreatefromgif($srcFile);
        } else {
            return 'The file is not a image';
        }

        // Rotate
        if($rotation > 0) {
            $rotation = '-'.$rotation;
        } else {
            $rotation = str_replace('-', '', $rotation);
        }
        $rotate = imagerotate($oSourceImage, $rotation , 0);
        
        //Path to save to
        $sPathToFile = $dstFile . $sFileName;
        //Check MimeType to create image
        if ($sMimeType == "image/jpeg") {
            imagejpeg($rotate, $sPathToFile, 80);
        } else if ($sMimeType == "image/png") {
            imagepng($rotate, $sPathToFile, 9);
        } else if ($sMimeType == "image/gif") {
            imagegif($rotate, $sPathToFile);
        } else {
            return 'Image could not be resized';
        }
        imagedestroy($oSourceImage);
        imagedestroy($rotate);
    }

    public function createRotationArray($imageType = NULL, $folderOriginal = NULL, $fileName = NULL, $destinationFolder = NULL, $image = NULL, $rotateImages = NULL) {
        $rotateimage = array();

        $rotateimage['imageType'] = $imageType;
        $rotateimage['originalLink'] = $folderOriginal . $fileName;
        $rotateimage['destinationFolder'] = $destinationFolder;

        if ($rotateImages == NULL) {
            $rotateImages = array();
        }
        $rotateImages[] = $rotateimage;
        return $rotateImages;
    }

}
