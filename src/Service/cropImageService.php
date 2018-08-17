<?php

namespace UploadImages\Service;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Session\Container;

/*
 * Entities
 */

//use UploadImages\Entity\Image;
//use UploadImages\Entity\ImageType;

class cropImageService implements cropImageServiceInterface {

    /**
     * @var \Blog\Service\PostServiceInterface
     */
    protected $config;
    protected $em;

    public function __construct($em, $config) {
        $this->config = $config;
        $this->em = $em;
    }

    public function uploadImage($image, $imageUploadSettings = NULL, $imageType = 'original', $Image = NULL, $isOriginal = 0) {

        //Check if provided image ia an array
        if (!is_array($image)) {
            return $this->translate('File is not a image');
        }

        $sUploadFolder = '';
        $iUploadeFileSize = 0;
        $aAllowedFileTypes = array();

        if ($imageUploadSettings == NULL) {
            $uploadFolder = $this->config['imageUploadSettings']['uploadFolder'];
        } else {
            $uploadFolder = $imageUploadSettings['uploadFolder'];
        }

        $sUploadFolder = 'public/' . $uploadFolder;
        $iUploadeFileSize = (int) $this->config['imageUploadSettings']['uploadeFileSize'];
        $aAllowedFileTypes = $this->config['imageUploadSettings']['allowedImageTypes'];


        //Target directory with file name
        $target_file = $sUploadFolder . basename($image["name"]);
        $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);

        // Check if image file is a actual image or fake image
        $check = getimagesize($image["tmp_name"]);
        if ($check === false) {
            return $this->translate('File is not a image');
        }

        // Check if folder excists and has the apropiete rights otherwise create and give rights
        if (!file_exists($sUploadFolder)) {
            mkdir($sUploadFolder, 0777, true);
        } elseif (!is_writable($sUploadFolder)) {
            chmod($sUploadFolder, 0777);
        }
        // Check if image file already exists
        if (file_exists($target_file)) {
            return 'File already excist';
        }
        // Check image file size
        if ($image["size"] > $iUploadeFileSize) {
            return 'Image not saved. File size exceeded';
        }


        $imageFileType = strtolower($imageFileType);
        // Allow certain file formats
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            return 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.';
        }

        if (copy($image["tmp_name"], $target_file)) {

            list($width, $height, $type, $attr) = getimagesize($image["tmp_name"]);

            $ImageFile = array();
            $ImageFile['imageFileName'] = $image["name"];
            $ImageFile['imageFolderName'] = $uploadFolder;
            $ImageFile['imgW'] = $width;
            $ImageFile['imgH'] = $height;

            //Create Image type
            return $this->createImageType($ImageFile, $imageType, $Image, 0, $isOriginal);
        } else {
            return 'Sorry, there was an error uploading your file.';
        }
    }

    public function resizeAndCropImage($sOriLocation = null, $sDestinationFolder = null, $iImgWidth = null, $iImgHeight = null, $imageType = 'original', $Image = null) {

        // Check if file exist
        if (!file_exists($sOriLocation)) {
            return 'File does not exist.';
        }

        // Check if the destionfolder is set. When false than Original location becomes Destination folder
        if ($sDestinationFolder == null) {
            $sDestinationFolder = dirname($sOriLocation);
        }

        // Check if the destination folder exist
        if (!is_dir($sDestinationFolder)) {
            return 'Folder ' . $sDestinationFolder . ' does not exist';
        }

        // Check if the directory has the appropiate rights
        if (substr(sprintf('%o', fileperms($sDestinationFolder)), -4) <> '0777') {
            return 'The folder does not has the appropirate rights to upload files.';
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
        return $this->createImageType($ImageFile, $imageType, $Image);

        // return $ImageFile;
    }

    public function CropImage($srcFile = null, $dstFile = null, $x, $y, $w, $h, $dw, $dh, $img_quality = 90) {

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

        // Switch between jpg, png or gif
        switch ($sMimeType) {
            case "image/jpeg":
                $img_r = imagecreatefromjpeg($srcFile);
                $dst_r = ImageCreateTrueColor($dw, $dh);
                imagecopyresampled($dst_r, $img_r, 0, 0, $x, $y, $dw, $dh, $w, $h);
                imagejpeg($dst_r, $dstFile . $sFileName, $img_quality);
                break;
            case "image/png":
                $img_r = imagecreatefrompng($srcFile);
                $dst_r = ImageCreateTrueColor($dw, $dh);

                $oTransparentIndex = imagecolortransparent($srcFile);
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

    public function setImageUploadSettings($imageUploadSettings) {
        if ($imageUploadSettings === NULL) {
            $this->imageUploadSettings = $this->getServiceLocator()->get('config');
        } else {

            $formatArray = array(
                'uploadFolder' => 'public/img/userFiles/',
                'uploadeFileSize' => '5000000000000000',
                'allowedImageTypes' => array(
                    'jpg',
                    'png',
                    'gif'
                )
            );

            if (array_diff($formatArray, $imageUploadSettings)) {
                return false;
            }

            $this->imageUploadSettings = $imageUploadSettings;
        }
    }

    public function createImage() {
        $image = new \UploadImages\Entity\Image();

        return $image;
    }

    public function createImageType($ImageFile = NULL, $imageTypeN = NULL, $Image = NULL, $crop = 0, $isOriginal = 0) {
        if (is_array($ImageFile) && !empty($imageTypeN)) {

            $imageFiles = array();

            if (!is_object($Image)) {
                $Image = $this->createImage();
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
            $Image->addImageType($imageType);
            $this->em->persist($imageType);

            //Save image
            $this->em->persist($Image);
            $this->em->flush();

            $imageFiles['imageType'] = $imageType;
            $imageFiles['image'] = $Image;

            return $imageFiles;
        } else {
            return 'You provided the wrong image details!';
        }
    }

    public function createCropArray($imageType = NULL, $folderOriginal = NULL, $fileName = NULL, $destinationFolder = NULL, $ImgW = NULL, $ImgH = NULL, $image = NULL, $cropImages = NULL) {
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

    public function createReCropArray($imageType = NULL, $folderOriginal = NULL, $fileName = NULL, $destinationFolder = NULL, $ImgW = NULL, $ImgH = NULL, $cropImages = NULL) {
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

    public function createReturnURL($route = NULL, $action = NULL, $id = NULL) {
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

    public function createContainerImages($cropImages = NULL, $returnURL = NULL) {
        $container = new Container('cropImages');
        $container->cropimages = $cropImages;
        $container->returnUrl = $returnURL;

        return true;
    }

}
