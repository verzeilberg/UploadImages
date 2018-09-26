<?php

namespace UploadImages;

use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;

return [
    'controllers' => [
        'factories' => [
            Controller\UploadImagesController::class => Controller\Factory\UploadImagesControllerFactory::class,
            Controller\AjaxImageController::class => Controller\Factory\AjaxImageControllerFactory::class,
        ],
        'aliases' => [
            'imagesbeheer' => Controller\UploadImagesController::class,
        ],
    ],
    'service_manager' => [
        'invokables' => [
            Service\cropImageServiceInterface::class => Service\cropImageService::class,
            Service\rotateImageServiceInterface::class => Service\rotateImageService::class,
            Service\imageServiceInterface::class => Service\imageService::class
        ],
    ],
    // The following section is new and should be added to your file
    'router' => [
        'routes' => [
            'images' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/image[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => 'imagesbeheer',
                        'action' => 'index',
                    ],
                ],
            ],
            'ajaximage' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/ajaximage[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => Controller\AjaxImageController::class
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'uploadimages' => __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    // The 'access_filter' key is used by the User module to restrict or permit
    // access to certain controller actions for unauthorized visitors.
    'access_filter' => [
        'controllers' => [
            'imagesbeheer' => [
                // to anyone.
                ['actions' => '*', 'allow' => '+images.manage']
            ],
            Controller\AjaxImageController::class => [
                // to anyone.
                ['actions' => '*', 'allow' => '+images.manage']
            ]
        ]
    ],
    'doctrine' => [
        'driver' => [
            __NAMESPACE__ . '_driver' => [
                'class' => AnnotationDriver::class,
                'cache' => 'array',
                'paths' => [__DIR__ . '/../src/Entity']
            ],
            'orm_default' => [
                'drivers' => [
                    __NAMESPACE__ . '\Entity' => __NAMESPACE__ . '_driver'
                ]
            ]
        ]
    ],
    'asset_manager' => [
        'resolver_configs' => [
            'paths' => [
                __DIR__ . '/../public',
            ],
        ],
    ],
    'imageUploadSettings' => [
        'uploadFolder' => 'img/userFiles/countries/original/',
        'uploadeFileSize' => '5000000000000000',
        'allowedImageTypes' => [
            'jpg',
            'png',
            'gif'
        ],
        'rootPath' => $_SERVER['DOCUMENT_ROOT'] . '/img/userFiles',
    ],
];
