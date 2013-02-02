<?php

namespace Samson\Bundle\ReleaseBundle;

use Samson\Bundle\ReleaseBundle\DependencyInjection\Compiler\PrepareReleaseCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Bart van den Burg <bart@samson-it.nl>
 */
class SamsonReleaseBundle extends Bundle
{

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new PrepareReleaseCompilerPass());
    }

}
