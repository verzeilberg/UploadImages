<?php

namespace UploadImages\Entity;

use Doctrine\ORM\Mapping as ORM;
use Laminas\Form\Annotation;

/**
 * Club
 *
 * @ORM\Entity
 * @ORM\Table(name="imagetypes")
 */
class ImageType {

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", length=11, name="id");
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $fileName;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $folder;
    
        /**
     * @ORM\Column(type="integer", length=11, nullable=true)
     */
    protected $width;
    
        /**
     * @ORM\Column(type="integer", length=11, nullable=true)
     */
    protected $height;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $imageTypeName;

    /**
     * @ORM\Column(name="is_crop", type="integer", length=1, nullable=false, options={"default"=0})
     */
    protected $isCrop = 0;
    
    /**
     * @ORM\Column(name="is_original", type="integer", length=1, nullable=false, options={"default"=0})
     */
    protected $isOriginal = 0;

    function getId() {
        return $this->id;
    }

    function getFileName() {
        return $this->fileName;
    }

    function getFolder() {
        return $this->folder;
    }

    function setId($id) {
        $this->id = $id;
    }

    function setFileName($fileName) {
        $this->fileName = $fileName;
    }

    function setFolder($folder) {
        $this->folder = $folder;
    }

    function getImageTypeName() {
        return $this->imageTypeName;
    }

    function setImageTypeName($imageTypeName) {
        $this->imageTypeName = $imageTypeName;
    }
    
    function getIsCrop() {
        return $this->isCrop;
    }

    function setIsCrop($isCrop) {
        $this->isCrop = $isCrop;
    }

    function getWidth() {
        return $this->width;
    }

    function getHeight() {
        return $this->height;
    }

    function setWidth($width) {
        $this->width = $width;
    }

    function setHeight($height) {
        $this->height = $height;
    }

    function getIsOriginal() {
        return $this->isOriginal;
    }

    function setIsOriginal($isOriginal) {
        $this->isOriginal = $isOriginal;
    }



}
