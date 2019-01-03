<?php

namespace UploadImages\Service;

interface cropImageServiceInterface {

    /**
     * Should return a set of all blog posts that we can iterate over. Single entries of the array are supposed to be
     * implementing \Blog\Model\PostInterface
     *
     * @return array|PostInterface[]
     */
    public function uploadImage($image, $imageUploadSettings);

    /**
     * Should return a set of all blog posts that we can iterate over. Single entries of the array are supposed to be
     * implementing \Blog\Model\PostInterface
     *
     * @return array|PostInterface[]
     */
    public function CropImage($srcFile, $dstFile, $x, $y, $w, $h, $dw, $dh, $img_quality);

    /**
     * Should return a single blog post
     *
     * @param  int $id Identifier of the Post that should be returned
     * @return PostInterface
     */
    public function resizeAndCropImage($sOriLocation, $sDestinationFolder, $iImgWidth, $iImgHeight);

}
