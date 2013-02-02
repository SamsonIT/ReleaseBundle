<?php

namespace Samson\Bundle\ReleaseBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * @author Bart van den Burg <bart@samson-it.nl>
 */
class PrepareReleaseCompilerPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('samson_release.prepare_release')) {
            return;
        }

        $definition = $container->getDefinition('samson_release.prepare_release');

        foreach ($container->findTaggedServiceIds('samson_release.prepare_release_excluder') as $id => $attributes) {
            $definition->addMethodCall('addExcluder', array(new Reference($id)));
        }
    }
}