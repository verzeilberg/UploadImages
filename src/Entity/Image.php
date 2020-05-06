<?php

namespace UploadImages\Entity;

use Doctrine\ORM\Mapping as ORM;
use Laminas\Form\Annotation;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Club
 *
 * @ORM\Entity
 * @ORM\Table(name="images")
 */
class Image {

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", length=11, name="id");
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $ImageId;

    /**
     * @Annotation\Required(false)
     * @Annotation\Type("Laminas\Form\Element\File")
     * * @Annotation\Options({
     * "label": "Upload afbeelding",
     * "label_attributes": {"class": "col-sm-4 col-md-4 col-lg-4 control-label"}
     * })
     */
    private $image;

    /**
     * @ORM\Column(name="name_image", type="string", length=255, nullable=true)
     * @Annotation\Required(false)
     * @Annotation\Options({
     * "label": "Afbeelding naam",
     * "label_attributes": {"class": "col-sm-4 col-md-4 col-lg-4 control-label"}
     * })
     * @Annotation\Attributes({"class":"form-control", "placeholder":"Afbeelding naam"})
     */
    protected $nameImage;

    /**
     * @ORM\Column(name="alt", type="string", length=255, nullable=true)
     * @Annotation\Required(false)
     * @Annotation\Options({
     * "label": "Alt tekst",
     * "label_attributes": {"class": "col-sm-4 col-md-4 col-lg-4 control-label"}
     * })
     * @Annotation\Attributes({"class":"form-control", "placeholder":"Alt tekst"})
     */
    protected $alt;

    /**
     * @ORM\Column(name="description_image",type="string", length=255, nullable=true)
     * @Annotation\Required(false)
     * @Annotation\Options({
     * "label": "Omschrijving",
     * "label_attributes": {"class": "col-sm-4 col-md-4 col-lg-4 control-label"}
     * })
     * @Annotation\Attributes({"class":"form-control", "placeholder":"Omschrijving"})
     */
    protected $descriptionImage;

    /**
     * @ORM\Column(name="sort_order", type="integer", length=11, nullable=true);
     * @Annotation\Required(false)
     */
    protected $sortOrder = 0;

    /**
     * Many images have Many imageTypes.
     * @ORM\ManyToMany(targetEntity="ImageType")
     * @ORM\JoinTable(name="image_imagetypes", 
     *      joinColumns={@ORM\JoinColumn(name="imageId", referencedColumnName="id", onDelete="CASCADE")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="imageTypeId", referencedColumnName="id", onDelete="CASCADE", unique=true)}
     *      )
     */
    private $imageTypes;

    public function __construct() {
        $this->imageTypes = new ArrayCollection();
    }

    function getImageTypes($imageTypeName = NULL) {
        if ($imageTypeName === NULL) {
            return $this->imageTypes;
        } else {


            $imageTypes = array();
            foreach ($this->imageTypes AS $imageType) {
                if ($imageType->getImageTypeName() == $imageTypeName) {
                    $imageTypes[] = $imageType;
                }
            }
            return $imageTypes;
        }
    }

    function setImageTypes($images) {
        $this->imageTypes = $images;
    }

    public function addImageType(ImageType $imageType) {
        if (!$this->imageTypes->contains($imageType)) {
            $this->imageTypes->add($imageType);
        }
        return $this;
    }

    public function removeImageType(ImageType $imageType) {
        if ($this->imageTypes->contains($imageType)) {
            $this->imageTypes->removeElement($imageType);
        }
        return $this;
    }

    function getAlt() {
        return $this->alt;
    }

    function setAlt($alt) {
        $this->alt = $alt;
    }

    function getBlogImageId() {
        return $this->blogImageId;
    }

    function setBlogImageId($blogImageId) {
        $this->blogImageId = $blogImageId;
    }

    function getSortOrder() {
        return $this->sortOrder;
    }

    function setSortOrder($sortOrder) {
        $this->sortOrder = $sortOrder;
    }

    function getNameImage() {
        return $this->nameImage;
    }

    function getDescriptionImage() {
        return $this->descriptionImage;
    }

    function setNameImage($nameImage) {
        $this->nameImage = $nameImage;
    }

    function setDescriptionImage($descriptionImage) {
        $this->descriptionImage = $descriptionImage;
    }

    function getImageId() {
        return $this->ImageId;
    }

    function setImageId($ImageId) {
        $this->ImageId = $ImageId;
    }

    function getImage() {
        return $this->image;
    }

    function setImage($image) {
        $this->image = $image;
    }

}
