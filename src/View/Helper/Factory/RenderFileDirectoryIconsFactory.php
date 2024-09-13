<?php

namespace UploadImages\View\Helper\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Application\View\Helper\Menu;
use UploadImages\View\Helper\RenderFileDirectoryIcons;

/**
 * Factory class responsible for creating instances of RenderFileDirectoryIcons.
 *
 * This factory retrieves the configuration from the container and uses it to
 * instantiate the RenderFileDirectoryIcons class.
 *
 * Implements the FactoryInterface to ensure that the factory method __invoke
 * is defined and can be used to create the service with dependencies provided
 * by the container.
 */
class RenderFileDirectoryIconsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): RenderFileDirectoryIcons
    {
        $config                 = $container->get('config');

        // Instantiate the helper.
        return new RenderFileDirectoryIcons (
            $config
        );
    }
}
