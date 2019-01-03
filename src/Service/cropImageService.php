<?php

namespace UploadImages\Service;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Session\Container;

/*
 * Entities
 */
use UploadImages\Entity\Image;

class cropImageService implements cropImageServiceInterface
{

    protected $config;
    protected $em;

    public function __construct($em, $config)
    {
        $this->config = $config;
        $this->em = $em;
    }

    public function uploadImage($image, $imageUploadSettingsKey = 'default', $imageType = 'original', $imageObject = NULL, $isOriginal = 0)
    {
        /**
         *
         * Check if $image is array
         *
         * @param image $image array
         * @return void
         *
         */
        if (!is_array($image)) {
            return 'File is not a image';
        }

        $aAllowedFileTypes = array();

        /**
         * Set imageUploadSettings from config in variable
         */
        $imageUploadSettings = $this->config['imageUploadSettings'];

        /**
         *
         * Check if uploadsettings excists in config array
         *
         * @param $imageUploadSettings string
         * @param $imageUploadSettings
         * @return void
         *
         */
        if (!array_key_exists($imageUploadSettingsKey, $imageUploadSettings)) {
            return 'Given settings does not excists';
        }

        /**
         * Set upload folder in variable
         */
        $uploadFolder = $imageUploadSettings[$imageUploadSettingsKey]['uploadFolder'];

        /**
         *
         * Check if upload folder is set in config array
         *
         * @param $uploadFolder string
         * @return void
         *
         */
        if (empty($uploadFolder)) {
            return 'Upload folder not set';
        }

        /**
         * Set public before upload folder
         */
        $uploadFolder = 'public/' . $uploadFolder;

        /**
         * Set upload file size in variable
         */
        $uploadFileSize = (int)$imageUploadSettings[$imageUploadSettingsKey]['uploadeFileSize'];

        /**
         *
         * Check if upload file size
         *
         * @param $uploadFileSize integer
         * @return void
         *
         */
        if (empty($uploadFileSize)) {
            return 'File size not set';
        }

        /**
         * Set allowed file types in variable
         */
        $allowedFileTypes = $imageUploadSettings[$imageUploadSettingsKey]['allowedImageTypes'];


        /**
         * Set targetfile
         */
        //Target directory with file name
        $targetFile = $uploadFolder . basename($image["name"]);

        /**
         * Check if file is a real file and not fake
         */
        $check = getimagesize($image["tmp_name"]);
        if ($check === false) {
            return 'File is not a image';
        }

        /**
         * Check if folder excists and has the apropiate rights otherwise create and give rights
         */
        if (!file_exists($uploadFolder)) {
            mkdir($uploadFolder, 0777, true);
        } elseif (!is_writable($uploadFolder)) {
            chmod($uploadFolder, 0777);
        }

        /**
         * Check if file  excists
         */
        if (file_exists($targetFile)) {
            return 'File already excist';
        }

        //@todo Rewrite name if file excists

        /**
         * Check if file sie is not exceeded
         */
        if ($image["size"] > $uploadFileSize) {
            return 'Image not saved. File size exceeded';
        }

        /*
         * Check if mime type is allowed for this upload
         */
        if (!in_array($image['type'], $allowedFileTypes)) {
            return 'File extension is not allowed';
        }

        if (copy($image["tmp_name"], $targetFile)) {

            list($width, $height, $type, $attr) = getimagesize($image["tmp_name"]);

            $ImageFile = array();
            $ImageFile['imageFileName'] = $image["name"];
            $ImageFile['imageFolderName'] = $uploadFolder;
            $ImageFile['imgW'] = $width;
            $ImageFile['imgH'] = $height;

            /**
             * Create Image type
             */
            return $this->createImageType($ImageFile, $imageType, $imageObject, 0, $isOriginal);
        } else {
            return 'Sorry, there was an error uploading your file.';
        }
    }

    /**
     * Resize and/or crops a image
     *
     * @param string $sOriLocation
     * Original location of the file
     * @param string $sDestinationFolder
     * Destination location of the file
     * @param integer $iImgWidth
     * The new width of the image
     * @param integer $iImgHeight
     * The new height of the image
     * @param string $imageType
     * Type of the image
     * @param object $imageObject
     * Image object for further use
     *
     * @return bool true if the filename exists and is
     * writable.
     */
    public function resizeAndCropImage($sOriLocation = null, $sDestinationFolder = null, $iImgWidth = null, $iImgHeight = null, $imageType = 'original', $imageObject = null)
    {

        // Check if file exist
        if (!file_exists($sOriLocation)) {
            return 'File does not exist.';
        }

        // Check if the destionfolder is set. When false than Original location becomes Destination folder
        if ($sDestinationFolder == null) {
            $sDestinationFolder = dirname($sOriLocation);
        }

        /**
         * Check if folder exists and has the appropiate rights otherwise create and give rights
         */
        if (!file_exists($sDestinationFolder)) {
            mkdir($sDestinationFolder, 0777, true);
        } elseif (!is_writable($sDestinationFolder)) {
            chmod($sDestinationFolder, 0777);
        }


        /*
          // Check is the file size is not to big Smaller than 50 mb
          // File size can be set in incl/config.php file
          if ($this->iFileSize  > MAX_FILE_SIZE) {
          $sStatusMessage = 'The file size is to big.';
          echo $sStatusMessage;
          exit;
          } */


        $sPathParts = pathinfo($sOriLocation);
        $sFileName = $sPathParts['basename'];
        $sMimeType = mime_content_type($sOriLocation);

        // Depending on wich file type is uploaded create a image
        if ($sMimeType == "image/jpeg") {
            $oSourceImage = imagecreatefromjpeg($sOriLocation);
        } else if ($sMimeType == "image/png") {
            $oSourceImage = imagecreatefrompng($sOriLocation);
        } else if ($sMimeType == "image/gif") {
            $oSourceImage = imagecreatefromgif($sOriLocation);
        } else {
            return 'The file is not a image';
        }

        // Get the widht and height of the uploade image        
        $aFileProps = getimagesize($sOriLocation);

        $iWidth = $aFileProps[0];
        $iHeight = $aFileProps[1];

        $original_aspect = $iWidth / $iHeight;
        $thumb_aspect = $iImgWidth / $iImgHeight;

        if ($original_aspect >= $thumb_aspect) {
            // If image is wider than thumbnail (in aspect ratio sense)
            $new_height = $iImgHeight;
            $new_width = $iWidth / ($iHeight / $iImgHeight);
        } else {
            // If the thumbnail is wider than the image
            $new_width = $iImgWidth;
            $new_height = $iHeight / ($iWidth / $iImgWidth);
        }

        # Create Temporary image with new Width and height
        # iNewWidth -> integer
        # iNewHeight -> integer
        $oTempImage = imagecreatetruecolor($iImgWidth, $iImgHeight);


        if ($sMimeType == "image/png" || $sMimeType == "image/gif") {

            $oTransparentIndex = imagecolortransparent($oSourceImage);
            if ($oTransparentIndex >= 0) { // GIF
                imagepalettecopy($oSourceImage, $oTempImage);
                imagefill($oTempImage, 0, 0, $oTransparentIndex);
                imagecolortransparent($oTempImage, $oTransparentIndex);
                imagetruecolortopalette($oTempImage, true, 256);
            } else { // PNG
                imagealphablending($oTempImage, false);
                imagesavealpha($oTempImage, true);
                $oTransparent = imagecolorallocatealpha($oTempImage, 255, 255, 255, 127);
                imagefilledrectangle($oTempImage, 0, 0, $iImgWidth, $iImgHeight, $oTransparent);
            }
        }

        // Resize and crop
        imagecopyresampled($oTempImage, $oSourceImage, 0 - ($new_width - $iImgWidth) / 2, 0 - ($new_height - $iImgHeight) / 2, 0, 0, $new_width, $new_height, $iWidth, $iHeight);

        $sPathToFile = $sDestinationFolder . '/' . $sFileName;

        //Check MimeType to create image
        if ($sMimeType == "image/jpeg") {
            imagejpeg($oTempImage, $sPathToFile, 80);
        } else if ($sMimeType == "image/png") {
            imagepng($oTempImage, $sPathToFile, 9);
        } else if ($sMimeType == "image/gif") {
            imagegif($oTempImage, $sPathToFile);
        } else {
            return 'Image could not be resized';
        }
        imagedestroy($oSourceImage);
        imagedestroy($oTempImage);

        $ImageFile = array();
        $ImageFile['imageFileName'] = $sFileName;
        $ImageFile['imageFolderName'] = $sDestinationFolder;
        $ImageFile['imgW'] = $iImgWidth;
        $ImageFile['imgH'] = $iImgHeight;


        //Create Image type
        return $this->createImageType($ImageFile, $imageType, $imageObject);

        // return $ImageFile;
    }

    public function CropImage($srcFile = null, $dstFile = null, $x, $y, $w, $h, $dw, $dh, $img_quality = 90)
    {

        // Check if file exist
        if (!file_exists($srcFile)) {
            return 'Could not find the original image';
        }

        // Check if the destionfolder is set. When false than Original location becomes Destination folder
        if ($dstFile == null) {
            $dstFile = dirname($srcFile);
        }

        // Check if the destination folder exist
        //if (!is_dir($dstFile)) {
            //return 'Folder ' . $dstFile . ' does not exist';
       // }

        /**
         * Check if folder excists and has the apropiate rights otherwise create and give rights
         */
        if (!file_exists($dstFile)) {
            mkdir($dstFile, 0777, true);
        } elseif (!is_writable($dstFile)) {
            chmod($dstFile, 0777);
        }

        //get file info like basename and mime type of the file
        $sPathParts = pathinfo($srcFile);
        $sFileName = $sPathParts['basename'];
        $sMimeType = mime_content_type($srcFile);

        // Switch between jpg, png or gif
        switch ($sMimeType) {
            case "image/jpeg":
                $img_r = imagecreatefromjpeg($srcFile);
                $dst_r = imagecreatetruecolor($dw, $dh);
                imagecopyresampled($dst_r, $img_r, 0, 0, $x, $y, $dw, $dh, $w, $h);
                imagejpeg($dst_r, $dstFile . $sFileName, $img_quality);
                break;
            case "image/png":

                $dst_r = imagecreatetruecolor($dw, $dh);
                $img_r = imagecreatefrompng($srcFile);
                $alpha_channel = imagecolorallocatealpha($img_r, 0, 0, 0, 127);
                $oTransparentIndex = imagecolortransparent($img_r);
                imagealphablending($dst_r, false);
                imagesavealpha($dst_r, true);
                $oTransparent = imagecolorallocatealpha($dst_r, 255, 255, 255, 127);
                imagefilledrectangle($dst_r, 0, 0, $dw, $dh, $oTransparent);


                imagecopyresampled($dst_r, $img_r, 0, 0, $x, $y, $dw, $dh, $w, $h);
                imagepng($dst_r, $dstFile . $sFileName, 9);
                break;
            case "image/gif":
                $img_r = imagecreatefromgif($srcFile);
                $dst_r = ImageCreateTrueColor($dw, $dh);

                $oTransparentIndex = imagecolortransparent($srcFile);
                imagepalettecopy($srcFile, $dst_r);
                imagefill($dst_r, 0, 0, $oTransparentIndex);
                imagecolortransparent($dst_r, $oTransparentIndex);
                imagetruecolortopalette($dst_r, true, 256);

                imagecopyresampled($dst_r, $img_r, 0, 0, $x, $y, $dw, $dh, $w, $h);
                imagegif($dst_r, $dstFile . $sFileName);
                break;
        }

        return true;
    }
    
    public function ResizeImage($srcFile = null, $dstFile = null, $dw = null, $dh = null, $imageType = null, $imageObject = null,  $img_quality = 90)
    {
        // Check if file exist
        if (!file_exists($srcFile)) {
            return 'Could not find the original image';
        }

        // Check if the destionfolder is set. When false than Original location becomes Destination folder
        if ($dstFile == null) {
            $dstFile = dirname($srcFile);
        }

        /**
         * Check if folder excists and has the apropiate rights otherwise create and give rights
         */
        if (!file_exists($dstFile)) {
            mkdir($dstFile, 0777, true);
        } elseif (!is_writable($dstFile)) {
            chmod($dstFile, 0777);
        }
        
        //get file info like basename and mime type of the file
        $sPathParts = pathinfo($srcFile);
        $sFileName = $sPathParts['basename'];
        $sMimeType = mime_content_type($srcFile);
        list($currentWidth, $currentHeight) = getimagesize($srcFile);
        
        
        if(!empty($dw) && !empty($dh)) {
            $newWidth = $dw;
            $newHeight = $dh;
        } else if (!empty($dw) || empty($dh)) {
            $newWidth = $dw;
            $ratio = (100 * $dw) / $currentWidth;
            $newHeight = ($currentHeight / 100) * $ratio;
        } else if (empty($dw) || !empty($dh)) {
            $newHeight = $dh;
            $ratio = (100 * $dh) / $currentHeight;
            $newWidth = ($currentWidth / 100) * $ratio;
        }
        
        // Switch between jpg, png or gif
        switch ($sMimeType) {
            case "image/jpeg":
                $img_r = imagecreatefromjpeg($srcFile);
                $dst_r = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($dst_r, $img_r, 0, 0, 0, 0, $newWidth, $newHeight, $currentWidth, $currentHeight);
                imagejpeg($dst_r, $dstFile . $sFileName, $img_quality);
                break;
            case "image/png":

                $dst_r = imagecreatetruecolor($newWidth, $newHeight);
                $img_r = imagecreatefrompng($srcFile);
                $alpha_channel = imagecolorallocatealpha($img_r, 0, 0, 0, 127);
                $oTransparentIndex = imagecolortransparent($img_r);
                imagealphablending($dst_r, false);
                imagesavealpha($dst_r, true);
                $oTransparent = imagecolorallocatealpha($dst_r, 255, 255, 255, 127);
                imagefilledrectangle($dst_r, 0, 0, $newWidth, $newHeight, $oTransparent);


                imagecopyresampled($dst_r, $img_r, 0, 0, 0, 0, $newWidth, $newHeight, $currentWidth, $currentHeight);
                imagepng($dst_r, $dstFile . $sFileName, 9);
                break;
            case "image/gif":
                $img_r = imagecreatefromgif($srcFile);
                $dst_r = ImageCreateTrueColor($newWidth, $newHeight);

                $oTransparentIndex = imagecolortransparent($srcFile);
                imagepalettecopy($srcFile, $dst_r);
                imagefill($dst_r, 0, 0, $oTransparentIndex);
                imagecolortransparent($dst_r, $oTransparentIndex);
                imagetruecolortopalette($dst_r, true, 256);

                imagecopyresampled($dst_r, $img_r, 0, 0, 0, 0, $newWidth, $newHeight, $currentWidth, $currentHeight);
                imagegif($dst_r, $dstFile . $sFileName);
                break;
        }
        
        $ImageFile = array();
        $ImageFile['imageFileName'] = $sFileName;
        $ImageFile['imageFolderName'] = $dstFile;
        $ImageFile['imgW'] = $newWidth;
        $ImageFile['imgH'] = $newHeight;


        //Create Image type
        return $this->createImageType($ImageFile, $imageType, $imageObject);

    }


    public function createImage()
    {
        $image = new Image();
        return $image;
    }

    public function createImageType($ImageFile = NULL, $imageTypeN = NULL, $imageObject = NULL, $crop = 0, $isOriginal = 0)
    {
        if (is_array($ImageFile) && !empty($imageTypeN)) {

            $imageFiles = array();

            if (!is_object($imageObject)) {
                $imageObject = $this->createImage();
            }

            $fileName = $ImageFile['imageFileName'];
            $width = $ImageFile['imgW'];
            $height = $ImageFile['imgH'];
            $folderOriginal = str_replace('public/', '', $ImageFile['imageFolderName']);
            $imageTypeName = $imageTypeN;
            $imageType = new \UploadImages\Entity\ImageType();
            $imageType->setIsCrop($crop);
            $imageType->setIsOriginal($isOriginal);
            $imageType->setHeight($height);
            $imageType->setWidth($width);
            $imageType->setFileName($fileName);
            $imageType->setFolder($folderOriginal);
            $imageType->setImageTypeName($imageTypeName);


            //Save image type
            $imageObject->addImageType($imageType);
            $this->em->persist($imageType);

            //Save image
            $this->em->persist($imageObject);
            $this->em->flush();

            $imageFiles['imageType'] = $imageType;
            $imageFiles['image'] = $imageObject;

            return $imageFiles;
        } else {
            return 'You provided the wrong image details!';
        }
    }

    public function createCropArray($imageType = NULL, $folderOriginal = NULL, $fileName = NULL, $destinationFolder = NULL, $ImgW = NULL, $ImgH = NULL, $image = NULL, $cropImages = NULL)
    {
        $cropimage = array();

        $cropimage['imageType'] = $imageType;
        $cropimage['originalLink'] = $folderOriginal . $fileName;
        $cropimage['destinationFolder'] = $destinationFolder;
        $cropimage['ImgW'] = $ImgW;
        $cropimage['ImgH'] = $ImgH;

        if ($cropImages == NULL) {
            $cropImages = array();
        }

        $cropImages[] = $cropimage;

        $imageFiles = $this->createImageType(array('imageFileName' => $fileName, 'imageFolderName' => $destinationFolder, 'imgW' => $ImgW, 'imgH' => $ImgH), $imageType, $image, 1);

        $imageFiles['cropImages'] = $cropImages;

        return $imageFiles;
    }

    public function createReCropArray($imageType = NULL, $folderOriginal = NULL, $fileName = NULL, $destinationFolder = NULL, $ImgW = NULL, $ImgH = NULL, $cropImages = NULL)
    {
        $cropimage = [];

        $cropimage['imageType'] = $imageType;
        $cropimage['originalLink'] = $folderOriginal . $fileName;
        $cropimage['destinationFolder'] = $destinationFolder;
        $cropimage['ImgW'] = $ImgW;
        $cropimage['ImgH'] = $ImgH;

        if ($cropImages == NULL) {
            $cropImages = array();
        }

        $cropImages[] = $cropimage;

        return $cropImages;
    }

    public function createReturnURL($route = NULL, $action = NULL, $id = NULL)
    {
        $returnURL = array();
        if (!empty($route)) {
            $returnURL['route'] = $route;
            if (!empty($action)) {
                $returnURL['action'] = $action;
            }
            if (!empty($id)) {
                $returnURL['id'] = $id;
            }
            return $returnURL;
        } else {
            return 'No route provided';
        }
    }

    public function createContainerImages($cropImages = NULL, $returnURL = NULL)
    {
        $container = new Container('cropImages');
        $container->cropimages = $cropImages;
        $container->returnUrl = $returnURL;

        return true;
    }

}
