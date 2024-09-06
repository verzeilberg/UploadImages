<?php

namespace UploadImages\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Doctrine\ORM\EntityManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use DoctrineORMModule\Form\Annotation\AnnotationBuilder;
use Laminas\Form\Form;
use Symfony\Component\VarDumper\VarDumper;
use UploadImages\Service\cropImageService;
use UploadImages\Service\cropImageServiceInterface;
use Laminas\Session\Container;

/*
 * Entities
 */

use UploadImages\Entity\Image;
use UploadImages\Service\imageService;
use UploadImages\Service\rotateImageService;

class AjaxImageController extends AbstractActionController
{

    protected cropImageService $cropImageService;
    protected rotateImageService $rotateImageService;
    protected imageService $imageService;
    protected $vhm;
    protected $em;
    protected $config;

    public function __construct(
        cropImageService   $cropImageService,
        rotateImageService $rotateImageService,
                           $vhm,
                           $em,
        imageService       $imageService,
                           $config
    )
    {
        $this->cropImageService = $cropImageService;
        $this->rotateImageService = $rotateImageService;
        $this->imageService = $imageService;
        $this->vhm = $vhm;
        $this->em = $em;
        $this->config = $config;
    }

    /**
     * @return JsonModel
     */
    public function deleteAction(): JsonModel
    {
        $errorMessage = '';
        $succes = true;
        $imageId = (int)$this->params()->fromPost('imageId', 0);

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

            foreach ($imageTypes as $imageType) {
                @unlink('public/' . $imageType->getFolder() . $imageType->getFileName());
                $this->em->remove($imageType);
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

    /**
     * @return JsonModel
     */
    public function getImageAction(): JsonModel
    {
        $errorMessage = '';
        $succes = true;
        $imageId = (int)$this->params()->fromPost('imageId', 0);
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

    /**
     * @return JsonModel
     */
    public function saveImageAction(): JsonModel
    {
        $errorMessage = '';
        $succes = true;
        $imageId = (int)$this->params()->fromPost('imageId', 0);
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

    /**
     * @return JsonModel
     */
    public function reCropAction(): JsonModel
    {
        $errorMessage = '';
        $succes = true;
        $imageId = (int)$this->params()->fromPost('imageId', 0);
        $route = $this->params()->fromPost('route');
        $action = $this->params()->fromPost('action');
        $id = (int)$this->params()->fromPost('id');
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
            $imageTypeName = $imageType['imageTypeName'];
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

    /**
     * @return JsonModel
     */
    public function rotateAction(): JsonModel
    {
        $errorMessage = '';
        $succes = true;
        $imageId = (int)$this->params()->fromPost('imageId', 0);
        $route = $this->params()->fromPost('route');
        $action = $this->params()->fromPost('action');
        $id = (int)$this->params()->fromPost('id');
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

        //Check if there are any items in the array
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

    /**
     * @return JsonModel
     */
    public function sortImagesAction(): JsonModel
    {
        $errorMessage = '';
        $succes = true;
        $images = $this->params()->fromPost('images', 0);

        if (count($images) == 0) {
            $errorMessage = 'Geen afbeeldingen geselecteerd';
            $succes = false;
        }

        foreach ($images as $index => $imageId) {
            $image = $this->em
                ->getRepository('UploadImages\Entity\Image')
                ->findOneBy(array('ImageId' => $imageId));

            $image->setSortOrder((int)$index);
            $this->em->persist($image);
            $this->em->flush();
            $succes = true;
        }

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
        ]);
    }

    /**
     * @return JsonModel
     */
    public function getAllDatabaseImagesAction(): JsonModel
    {
        $result = [];
        $imageTypes = $this->imageService->getAllImageTypes();

        $key = 0;
        foreach ($imageTypes as $imageType) {

            $result[$key]['id'] = $imageType->getId();
            $result[$key]['name'] = $imageType->getFileName();
            $result[$key]['folder'] = $imageType->getFolder();

            $key++;
        }

        return new JsonModel([
            'result' => $result
        ]);
    }

    /**
     * @return JsonModel
     */
    public function getAllServerImagesAction(): JsonModel
    {
        $result = [];
        $rootPath = $this->config['imageUploadSettings']['rootPath'];
        $images = $this->imageService->getAllImageFromFolder($rootPath);

        $key = 0;
        foreach ($images as $image) {
            $imageExist = $this->imageService->checkFileExistInDatabase($image['url']);
            if (!$imageExist) {
                $result[$key]['url'] = $image['url'];
                $key++;
            }
        }

        return new JsonModel([
            'result' => $result
        ]);
    }


    public function checkServerImageAction()
    {
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

    public function checkDatabaseImageAction()
    {
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

    /**
     * @return JsonModel
     */
    public function deleteImageFromServerAction(): JsonModel
    {
        $errorMessage = '';
        $id = $this->params()->fromPost('id', 0);
        $url = $this->params()->fromPost('url', 0);
        $succes = $this->imageService->deleteImageFromServer($url);

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
            'id' => $id
        ]);
    }

    public function deleteImagesFromServerAction(): JsonModel
    {
        $errorMessage = '';
        $images = $this->params()->fromPost('images', []);
        $result = [];
        foreach ($images AS $id => $image) {
            $succes = $this->imageService->deleteImageFromServer($image[1]);
            $result[$image[0]] = $succes;
        }
        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
            'result' => $result
        ]);
    }

    /**
     * Deletes an image from the database.
     *
     * This method retrieves the image ID and URL from the request parameters and uses them
     * to retrieve the corresponding image type from the image service. The image type is
     * then deleted from the database. The method returns a JSON response containing the
     * error message (if any), success status, and the ID of the deleted image.
     *
     * @return JsonModel Returns a JSON response containing the error message (if any),
     *                  success status, and the ID of the deleted image.
     */
    public function deleteImageFromDatabaseAction(): JsonModel
    {
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

    /**
     * Deletes multiple image types from the database.
     * @return JsonModel The JSON model containing the success status and error message, if any.
     */
    public function deleteImagesFromDatabaseAction(): JsonModel
    {
        $errorMessage = '';
        $succes = true;
        $images = $this->params()->fromPost('images', []);
        $imageMessages = [];
        foreach($images as $index => $image) {
            $imageType = $this->imageService->getImageTypeById($image[1]);
            $succes = $this->imageService->deleteImageType($imageType);
            $imageMessages[$index]['rowIndex'] = $image[0];
            $imageMessages[$index]['succes'] = $succes;
        }

        return new JsonModel([
            'errorMessage' => $errorMessage,
            'succes' => $succes,
            'imageMessages' => $imageMessages
        ]);
    }

}
