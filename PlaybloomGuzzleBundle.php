<?php

namespace Playbloom\Bundle\GuzzleBundle;

use Playbloom\Bundle\GuzzleBundle\DependencyInjection\Compiler\ClientPluginPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Playbloom Guzzle Bundle
 *
 * @author Ludovic Fleury <ludo.fleury@gmail.com>
 */
class PlaybloomGuzzleBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ClientPluginPass());
    }
}
