<?php

namespace UploadImages\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use Doctrine\ORM\EntityManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use DoctrineORMModule\Form\Annotation\AnnotationBuilder;
use Laminas\Form\Form;
use UploadImages\Service\cropImageService;
use Laminas\Session\Container;

/*
 * Entities
 */

use UploadImages\Entity\Image;

class UploadImagesController extends AbstractActionController
{

    protected $cropImageService;
    protected $rotateImageService;
    protected $imageService;
    protected $vhm;
    protected $em;
    protected $config;

    public function __construct(cropImageService $cropImageService, $rotateImageService, $vhm, $em, $imageService, $config)
    {
        $this->cropImageService = $cropImageService;
        $this->rotateImageService = $rotateImageService;
        $this->imageService = $imageService;
        $this->vhm = $vhm;
        $this->em = $em;
        $this->config = $config;
    }

    public function indexAction()
    {
        $this->layout('layout/beheer');


        return new ViewModel(
            array()
        );
    }

    public function serverCheckAction()
    {
        $this->layout('layout/beheer');
        $this->vhm->get('headLink')->appendStylesheet('/css/upload-image.css');
        $this->vhm->get('headScript')->appendFile('/js/uploadImages.js');

        return new ViewModel();
    }

    public function fileCheckAction()
    {
        $this->layout('layout/beheer');
        $this->vhm->get('headLink')->appendStylesheet('/css/upload-image.css');
        $this->vhm->get('headScript')->appendFile('/js/uploadImages.js');

        return new ViewModel();
    }

    public function cropAction()
    {
        $this->layout('layout/crop');
        $this->vhm->get('headScript')->appendFile('/js/jquery.Jcrop.min.js');
        $this->vhm->get('headLink')->appendStylesheet('/css/jCrop/jquery.Jcrop.min.css');
        $container = new Container('cropImages');

        $aCropDetails = $container->offsetGet('cropimages')??[];
        $aReturnURL = $container->offsetGet('returnUrl');
        $iXcrops = count($aCropDetails);

        if (empty($aCropDetails)) {
            return $this->createRedirectLink($aReturnURL);
        }

        //Get the first item in the array
        $oCropDetails = $aCropDetails[0];

        //Split session array into varibles
        $sImageToBeCropped = $oCropDetails['originalLink']; //link of the image that must be cropped
        $sCropReference = $oCropDetails['imageType']; //Reference of the crop
        $sDestionationFolderCroppedImage = $oCropDetails['destinationFolder']; //Folder where the image has to be saved
        $iImgW = (int)$oCropDetails['ImgW']; //Image width
        $iImgH = (int)$oCropDetails['ImgH']; //Image height
        // Get the widht and height of the orignal image
        $aFileProps = getimagesize('public/' . $sImageToBeCropped);

        $iWidth = (int)$aFileProps[0];
        $iHeight = (int)$aFileProps[1];

        if ($iImgW > $iWidth || $iImgH > $iHeight) {
            array_shift($aCropDetails);
            $container->cropimages = $aCropDetails;
            $this->flashMessenger()->addErrorMessage('Crop size is to large for orginal image');
            if (empty($aCropDetails)) {
                $container->getManager()->getStorage()->clear('cropImages');
                return $this->createRedirectLink($aReturnURL);
            } else {
                return $this->createRedirectLink($aReturnURL);
            }
        }


        //if user crops image
        if ($this->getRequest()->isPost()) {
            $x = $this->getRequest()->getPost('x');
            $y = $this->getRequest()->getPost('y');
            $w = $this->getRequest()->getPost('w');
            $h = $this->getRequest()->getPost('h');

            //Crop image
            $result = $this->cropImageService->CropImage($x, $y, $w, $h, $iImgW, $iImgH, 90, 'public/' . $sImageToBeCropped, $sDestionationFolderCroppedImage);

            # Delete the first item in the array
            array_shift($aCropDetails);
            $container->cropimages = $aCropDetails;

            # Check if the array is empty
            if (empty($aCropDetails)) {
                $container->getManager()->getStorage()->clear('cropImages');
                # set status update
                return $this->createRedirectLink($aReturnURL);
            } else {
                return $this->redirect()->toRoute('beheer/images', array('action' => 'crop'));
            }
        }

        return new ViewModel(
            array(
                'sCropReference' => $sCropReference,
                'sImageToBeCropped' => $sImageToBeCropped,
                'sDestionationFolderCroppedImage' => $sDestionationFolderCroppedImage,
                'iXcrops' => $iXcrops,
                'iImgW' => $iImgW,
                'iImgH' => $iImgH
            )
        );
    }

    public function rotateAction()
    {
        $this->layout('layout/rotate');
        $container = new Container('rotateImage');
        $aRotateDetails = $container->offsetGet('rotateImage');
        $aRotateImages = $container->offsetGet('rotateImages');
        $aReturnURL = $container->offsetGet('returnUrl');

        if ($this->getRequest()->isPost()) {
            //Get rotation from form
            $rotation = (int)$this->getRequest()->getPost()['rotation'];

            if ($rotation != 0) {
                foreach ($aRotateImages AS $rotateImage) {
                    $imageToBeRotated = $rotateImage["folder"] . $rotateImage["fileName"];
                    $destionationFolderCroppedImage = 'public/' . $rotateImage["folder"];
                    $result = $this->rotateImageService->rotateImage('public/' . $imageToBeRotated, $destionationFolderCroppedImage, $rotation);
                }
            }

            $container->getManager()->getStorage()->clear('rotateImage');
            # set status update
            return $this->createRedirectLink($aReturnURL);
        }


        return new ViewModel(
            array(
                'aRotateDetails' => $aRotateDetails,
                'aReturnURL' => $aReturnURL,
            )
        );
    }

    public function createRedirectLink($aReturnURL = NULL)
    {
        if ($aReturnURL === NULL) {
            $this->redirect()->toRoute('home');
        } else {

            var_dump($aReturnURL);

            $route = $aReturnURL['route'];
            unset($aReturnURL['route']);
            return $this->redirect()->toRoute($route, $aReturnURL);
        }
    }

}
