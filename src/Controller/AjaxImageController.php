<?php

namespace UploadImages\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Doctrine\ORM\EntityManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use DoctrineORMModule\Form\Annotation\AnnotationBuilder;
use Zend\Form\Form;
use UploadImages\Service\cropImageServiceInterface;
use Zend\Session\Container;

/*
 * Entities
 */
use UploadImages\Entity\Image;

class AjaxImageController extends AbstractActionController {

    protected $cropImageService;
    protected $rotateImageService;
    protected $imageService;
    protected $vhm;
    protected $em;
    protected $config;

    public function __construct(cropImageServiceInterface $cropImageService, $rotateImageService, $vhm, $em, $imageService, $config) {
        $this->cropImageService = $cropImageService;
        $this->rotateImageService = $rotateImageService;
        $this->imageService = $imageService;
        $this->vhm = $vhm;
        $this->em = $em;
        $this->config = $config;
    }

    public function deleteAction() {
        $errorMessage = '';
        $succes = true;
        $imageId = (int) $this->params()->fromPost('imageId', 0);
        
        if (empty($imageId) || $imageId < 0) {
            $errorMessage = 'Geen afbeelding id meegegeven!';
            $succes = false;
        }
        
        $image = $this->em
                ->getRepository('UploadImages\Entity\Image')
                ->findOneBy(array('ImageId' => $imageId));
        
        

        if (!is_object($image)) {
            $errorMessage = 'Geen afbeelding gevonden met id ' . $imageId . ' gevonden!';
            $succes = false;
        } else {
            $imageTypes = $image->getImageTypes();
            
            foreach ($imageTypes AS $imageType) {
                @unlink('public/' . $imageType->getFolder() . $imageType->getFileName());
                $this->em->remove($imageType);
                $this->em->flush();
            }

            $this->em->remove($image);
            $this->em->flush();

            $succes = true;
        }
        
        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
            'imageId' => $imageId
        ]);
    }

    public function getImageAction() {
        $errorMessage = '';
        $succes = true;
        $imageId = (int) $this->params()->fromPost('imageId', 0);
        if (empty($imageId) || $imageId == 0) {
            $errorMessage = 'Geen afbeelding id meegegeven!';
            $succes = false;
        }

        $image = $this->em
                ->getRepository('UploadImages\Entity\Image')
                ->findOneBy(array('ImageId' => $imageId));

        if (!is_object($image)) {
            $errorMessage = 'Geen afbeelding gevonden met id ' . $imageId . ' gevonden!';
            $succes = false;
        } else {
            $imageDetails = [];
            $imageDetails['imageName'] = $image->getNameImage();
            $imageDetails['imageAlt'] = $image->getAlt();
            $imageDetails['imageDescription'] = $image->getDescriptionImage();
            $succes = true;
        }
        
        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
            'imageId' => $imageId,
            'imageDetails' => $imageDetails
        ]);
    }

    public function saveImageAction() {
        $errorMessage = '';
        $succes = true;
        $imageId = (int) $this->params()->fromPost('imageId', 0);
        if (empty($imageId) || $imageId == 0) {
            $errorMessage = 'Geen afbeelding id meegegeven!';
            $succes = false;
        }

        $image = $this->em
                ->getRepository('UploadImages\Entity\Image')
                ->findOneBy(array('ImageId' => $imageId));

        if (!is_object($image)) {
            $errorMessage = 'Geen afbeelding met id ' . $imageId . ' gevonden!';
            $succes = false;
        } else {
            $nameImage = $this->params()->fromPost('nameImage');
            $alt = $this->params()->fromPost('alt');
            $descriptionImage = $this->params()->fromPost('descriptionImage');
            $image->setNameImage($nameImage);
            $image->setAlt($alt);
            $image->setDescriptionImage($descriptionImage);
            $this->em->persist($image);
            $this->em->flush();

            $succes = true;
        }

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
            'imageId' => $imageId
        ]);
    }

    public function reCropAction() {
        $errorMessage = '';
        $succes = true;
        $imageId = (int) $this->params()->fromPost('imageId', 0);
        $route = $this->params()->fromPost('route');
        $action = $this->params()->fromPost('action');
        $id = (int) $this->params()->fromPost('id');
        if (empty($imageId) || $imageId == 0) {
            $errorMessage = 'Geen afbeelding id meegegeven!';
            $succes = false;
        }

        //Get original image file
        $qb = $this->em->getRepository('UploadImages\Entity\Image')->createQueryBuilder('i');
        $qb->select('it.fileName, it.folder');
        $qb->join('i.imageTypes', 'it');
        $qb->where('it.isOriginal = 1');
        $qb->andWhere('i.ImageId = ' . $imageId);
        $imageOriginal = $qb->getQuery()->getSingleResult();

        $originalFileName = $imageOriginal['fileName'];
        $originalFolder = $imageOriginal['folder'];

        //Get all Crop images
        $qb = $this->em->getRepository('UploadImages\Entity\Image')->createQueryBuilder('i');
        $qb->select('it.fileName, it.folder, it.imageTypeName, it.width, it.height');
        $qb->join('i.imageTypes', 'it');
        $qb->where('it.isCrop = 1');
        $qb->andWhere('i.ImageId = ' . $imageId);
        $imageTypes = $qb->getQuery()->getArrayResult();

        //Loop trough image type's to create recrop array for session
        $cropImages = '';
        foreach ($imageTypes as $imageType) {
            $width = $imageType['width'];
            $height = $imageType['height'];
            $imageTypeName = ['imageTypeName'];
            $folder = $imageType['folder'];
            $cropImages = $this->cropImageService->createReCropArray($imageTypeName, $originalFolder, $originalFileName, 'public/' . $folder, $width, $height, $cropImages);
        }

        //Create return url after cropping images
        $returnURL = $this->cropImageService->createReturnURL($route, $action, $id);

        //Create session container for crop
        $this->cropImageService->createContainerImages($cropImages, $returnURL);

        //Check if there any items in the array
        if (is_array($cropImages)) {
            $succes = true;
        } else {
            $errorMessage = 'Geen afbeeldingen voor crop gevonden.';
            $succes = false;
        }

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
        ]);
    }

    public function rotateAction() {
        $errorMessage = '';
        $succes = true;
        $imageId = (int) $this->params()->fromPost('imageId', 0);
        $route = $this->params()->fromPost('route');
        $action = $this->params()->fromPost('action');
        $id = (int) $this->params()->fromPost('id');
        if (empty($imageId) || $imageId == 0) {
            $errorMessage = 'Geen afbeelding id meegegeven!';
            $succes = false;
        }

        $imageOriginal = $this->imageService->getOriginalImageByImageID($imageId);
        $originalFileName = $imageOriginal['fileName'];
        $originalFolder = $imageOriginal['folder'];

        $imageTypes = $this->imageService->getImageTypesByImageID($imageId);
        
        //Create return url after cropping images
        $returnURL = $this->cropImageService->createReturnURL($route, $action, $id);

        //Create session container for crop
        $this->rotateImageService->createContainerImage($imageOriginal, $imageTypes, $returnURL);

        //Check if there any items in the array
        if (is_array($imageTypes)) {
            $succes = true;
        } else {
            $errorMessage = 'Geen afbeeldingen voor rotatie gevonden.';
            $succes = false;
        }

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
        ]);
    }

    public function sortImagesAction() {
        $errorMessage = '';
        $succes = true;
        $images = $this->params()->fromPost('images', 0);

        if (count($images) == 0) {
            $errorMessage = 'Geen afbeeldingen geselecteerd';
            $succes = false;
        }

        foreach ($images AS $index => $imageId) {
            $image = $this->em
                    ->getRepository('UploadImages\Entity\Image')
                    ->findOneBy(array('ImageId' => $imageId));

            $image->setSortOrder((int) $index);
            $this->em->persist($image);
            $this->em->flush();
            $succes = true;
        }

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
        ]);
    }

    public function checkServerImageAction() {
        $errorMessage = '';
        $succes = true;
        $id = $this->params()->fromPost('id', 0);
        $imageName = $this->params()->fromPost('name', 0);
        $imageFolder = $this->params()->fromPost('folder', 0);

        $succes = $this->imageService->findImageByPathAndName($imageFolder, $imageName);

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
            'id' => $id
        ]);
    }

    public function checkDatabseImageAction() {
        $errorMessage = '';
        $succes = true;
        $id = $this->params()->fromPost('id', 0);
        $imageName = $this->params()->fromPost('name', 0);
        $imageFolder = $this->params()->fromPost('folder', 0);
        $rootPath = $this->config['imageUploadSettings']['rootPath'];

        $succes = $this->imageService->checkFileExcist($imageFolder, $imageName, $rootPath);

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
            'id' => $id
        ]);
    }

    public function deleteImageFromServerAction() {
        $errorMessage = '';
        $succes = true;
        $id = $this->params()->fromPost('id', 0);
        $url = $this->params()->fromPost('url', 0);

        $succes = $this->imageService->deleteImageFromServer($url);

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
            'id' => $id
        ]);
    }

    public function deleteImageFromDatabaseAction() {
        $errorMessage = '';
        $succes = true;
        $id = $this->params()->fromPost('id', 0);
        $url = $this->params()->fromPost('url', 0);

        $imageType = $this->imageService->getImageTypeById($id);
        $succes = $this->imageService->deleteImageType($imageType);

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
            'id' => $id
        ]);
    }

}
