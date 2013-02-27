<?php

namespace Samson\Bundle\ReleaseBundle\Command;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * @author Bart van den Burg <bart@samson-it.nl>
 */
class CreateVersionTagCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('samson:release:tag');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $determiner = $this->getContainer()->get('samson_release.version_determiner');
        $version = $determiner->determineVersion();
        
        if (!preg_match('/^(\d+\.\d+\.\d+)(?:-(dev))?/', $version, $m)) {
            throw new \RuntimeException("Cannot make a nice tag out of ".$version);
        }
        
        if (count($m) < 3 || 'dev' !== $m[2]) {
            throw new \RuntimeException("We appear not to be on a dev version...");
        }
        
        $nextVersion = $m[1];

        $ef = new \Symfony\Component\Process\ExecutableFinder;
        $git = $ef->find('git', null, array('/usr/bin'));
        
        /* @var $helper \Symfony\Component\Console\Helper\DialogHelper */
        $helper = $this->getHelper('dialog');
        $default = 'Version '.$nextVersion;
        $message = $helper->ask($output, 'Message for the annotated tag ['.$default.'] ', $default);
        
        /* @var $p \Symfony\Component\Process\Process */
        $p = \Symfony\Component\Process\ProcessBuilder::create(array($git, 'tag', '-am', '"'.addslashes($message).'"', $nextVersion))->getProcess();
        $p->run();
        
        if ($p->getExitCode()) {
            $output->write($p->getErrorOutput());
            throw new \RuntimeException('Git gave an error!');
        }
    }
}