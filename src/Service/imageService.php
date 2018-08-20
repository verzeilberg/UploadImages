<?php

namespace UploadImages\Service;

use Zend\ServiceManager\ServiceLocatorInterface;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use DoctrineORMModule\Form\Annotation\AnnotationBuilder;

/*
 * Entities
 */
use UploadImages\Entity\Image;
use UploadImages\Entity\ImageType;

class imageService implements imageServiceInterface {

    protected $config;
    protected $em;

    public function __construct($em, $config) {
        $this->config = $config;
        $this->em = $em;
    }

    /**
     *
     * Create image object
     *
     * @return   object
     *
     */
    public function createImage() {
        return new Image();
    }

    /**
     *
     * Create image form
     *
     * @param    image $image object
     * @return   form
     *
     */
    public function createImageForm($image) {
        $builder = new AnnotationBuilder($this->em);
        $formImage = $builder->createForm($image);
        $formImage->setHydrator(new DoctrineHydrator($this->em, 'UploadImages\Entity\Image'));
        $formImage->bind($image);

        return $formImage;
    }

    /*
     * 
     * Delete image object
     * 
     * @param type $image object
     * @return void
     * 
     */

    public function deleteImage($image = NULL) {
        if (is_object($image)) {
            $imageTypes = $image->getImageTypes();
            foreach ($imageTypes AS $imageType) {
                @unlink('public/' . $imageType->getFolder() . $imageType->getFileName());
                $this->em->remove($imageType);
                $this->em->flush();
            }
            $this->em->remove($image);
            $this->em->flush();
            return true;
        } else {
            return false;
        }
    }

    /*
     * 
     * Delete image file from server
     * 
     * @param type $imageUrl string
     * @return void
     * 
     */

    public function deleteImageFromServer($imageUrl = null) {
        if (!empty($imageUrl)) {
            $result = unlink('public/' . $imageUrl);

            return $result;
        } else {
            return false;
        }
    }

    /**
     * 
     * Delete array of images
     *
     * @param type $images array
     * @return void
     * 
     */
    public function deleteImages($images = NULL) {
        if (!empty($images)) {
            foreach ($images AS $image) {
                $imageTypes = $image->getImageTypes();
                foreach ($imageTypes AS $imageType) {
                    @unlink('public/' . $imageType->getFolder() . $imageType->getFileName());
                    $this->em->remove($imageType);
                    $this->em->flush();
                }
                $this->em->remove($image);
                $this->em->flush();
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * Create redirect URL
     *
     * @param    aReturnURL $aReturnURL array
     * @return   redirect
     *
     */
    public function createRedirectLink($aReturnURL = NULL) {
        if ($aReturnURL === NULL) {
            $this->redirect()->toRoute('home');
        } else {
            $route = $aReturnURL['route'];
            unset($aReturnURL['route']);
            return $this->redirect()->toRoute($route, $aReturnURL);
        }
    }

    /**
     *
     * Get all images from specific folder
     *
     * @param    rootPath $rootPath string
     * @return   array
     *
     */
    public function getAllImageFromFolder($rootPath) {
        $images = [];
        $dir = new \DirectoryIterator($rootPath);

        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot()) {
                if ($fileinfo->getType() == 'dir') {
                    $images = array_merge($images, $this->getAllImageFromFolder($rootPath . '/' . $fileinfo->getFilename()));
                } else {
                    $image = [];
                    $image['url'] = str_replace('/home/hosting/sander/WWW/public//', '', $fileinfo->getPathname());
                    $image['fileName'] = $fileinfo->getFilename();
                    $image['fileSize'] = $fileinfo->getSize();
                    $image['type'] = $fileinfo->getType();
                    $image['ext'] = $fileinfo->getExtension();
                    $image['baseName'] = $fileinfo->getBasename();

                    $images[] = $image;
                }
            }
        }
        return $images;
    }

    /**
     *
     * Get all images
     *
     * @return   array
     *
     */
    public function getImages() {
        $images = $this->em->getRepository(ImageType::class)
                ->findBy([], ['fileName' => 'DESC']);

        return $images;
    }

    /**
     *
     * Get all imageTypes filename and folder by ImageID
     *
     * @var $imageId Image id
     * 
     * @return   array
     *
     */
    public function getOriginalImageByImageID($imageId) {
        //Get original image file
        $qb = $this->em->getRepository('UploadImages\Entity\Image')->createQueryBuilder('i');
        $qb->select('it.fileName, it.folder');
        $qb->join('i.imageTypes', 'it');
        $qb->where('it.isOriginal = 1');
        $qb->andWhere('i.ImageId = ' . $imageId);
        $imageOriginal = $qb->getQuery()->getSingleResult();
        
        return $imageOriginal;
    }

    /**
     *
     * Get all imageTypes filename and folder by ImageID
     *
     * @var $imageId Image id
     * 
     * @return   array
     *
     */
    public function getImageTypesByImageID($imageId) {
        //Get all Crop images
        $qb = $this->em->getRepository('UploadImages\Entity\Image')->createQueryBuilder('i');
        $qb->select('it.fileName, it.folder');
        $qb->join('i.imageTypes', 'it');
        $qb->where('i.ImageId = ' . $imageId);
        $imageTypes = $qb->getQuery()->getArrayResult();

        return $imageTypes;
    }

    /**
     *
     * Find image by path and image name
     *
     * @param    path $path string
     * @param    name $name string
     * @return   boolean
     *
     */
    public function findImageByPathAndName($path = null, $name = null) {
        $path = trim($path);
        $name = trim($name);
        if (!empty($path) && !empty($name)) {
            $qb = $this->em->getRepository('UploadImages\Entity\ImageType')->createQueryBuilder('it');
            $qb->where('it.fileName = :name');
            $qb->andWhere('it.folder = :path');
            $qb->setParameter('path', $path);
            $qb->setParameter('name', $name);
            $query = $qb->getQuery();
            $single = $query->getScalarResult();
            if (empty($single)) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     *
     * Check if images excist on server
     *
     * @param    path $path string
     * @param    name $name string
     * @return   boolean
     *
     */
    public function checkFileExcist($path = null, $name = null, $rootPath = null) {
        $path = trim($path);
        $name = trim($name);
        $rootPath = trim($rootPath);
        if (!empty($path) && !empty($name) && !empty($rootPath)) {
            $fullUrl = $_SERVER['DOCUMENT_ROOT'] . $path . $name;
            if (file_exists($fullUrl)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     *
     * Get imageType object based on id
     *
     * @param       id  $id The id to fetch the imageType from the database
     * @return      object
     *
     */
    public function getImageTypeById($id) {
        $imageType = $this->em->getRepository(ImageType::class)
                ->findOneBy(['id' => $id], []);

        return $imageType;
    }

    /**
     * 
     * Delete imageType
     *
     * @param type $imageType object
     * @return void
     * 
     */
    public function deleteImageType($imageType = NULL) {

        if ($imageType != null) {
            $this->em->remove($imageType);
            $this->em->flush();

            return true;
        } else {
            return false;
        }
    }

}
