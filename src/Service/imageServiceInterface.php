<?php

namespace UploadImages\Service;

interface imageServiceInterface {

    /**
     *
     * Create image object
     *
     * @return   object
     *
     */
    public function createImage();

    /**
     *
     * Create image form
     *
     * @param    image $image object
     * @return   form
     *
     */
    public function createImageForm($image);

    /*
     * 
     * Delete image file from server
     * 
     * @param type $imageUrl string
     * @return void
     * 
     */
    public function deleteImageFromServer($imageUrl = null);

    /**
     * 
     * Delete image object
     * 
     * @param type $image object
     * @return void
     * 
     */
    public function deleteImage($image = NULL);

    /**
     * 
     * Delete array of images
     *
     * @param type $images array
     * @return void
     * 
     */
    public function deleteImages($images = NULL);

    /**
     *
     * Create redirect URL
     *
     * @param    aReturnURL $aReturnURL array
     * @return   redirect
     *
     */
    public function createRedirectLink($aReturnURL = NULL);

    /**
     *
     * Get all images from specific folder
     *
     * @param    rootPath $rootPath string
     * @return   array
     *
     */
    public function getAllImageFromFolder($rootPath);

    /**
     *
     * Get all images
     *
     * @return   array
     *
     */
    public function getImages();

    /**
     *
     * Find image by path and image name
     *
     * @param    path $path string
     * @param    name $name string
     * @return   boolean
     *
     */
    public function findImageByPathAndName($path = null, $name = null);
}
