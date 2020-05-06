<?php

namespace UploadImages\Controller\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use UploadImages\Service\cropImageService;
use UploadImages\Service\rotateImageService;
use UploadImages\Service\imageService;
use UploadImages\Controller\AjaxImageController;

/**
 * This is the factory for AuthController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AjaxImageControllerFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {

        $em = $container->get('doctrine.entitymanager.orm_default');
        $config = $container->get('config');
        $cis = new cropImageService($em, $config);
        $ris = new rotateImageService($em, $config);
        $is = new imageService($em, $config);
        $em = $container->get('doctrine.entitymanager.orm_default');
        $vhm = $container->get('ViewHelperManager');
        return new AjaxImageController($cis, $ris, $vhm, $em, $is, $config);
    }

}
