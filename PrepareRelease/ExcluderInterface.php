<?php

namespace Samson\Bundle\ReleaseBundle\PrepareRelease;

use Symfony\Component\Finder\Finder;

/**
 * @author Bart van den Burg <bart@samson-it.nl>
 */
interface ExcluderInterface
{

    public function process(Finder $finder);
}