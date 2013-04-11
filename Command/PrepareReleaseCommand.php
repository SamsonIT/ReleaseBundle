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
class PrepareReleaseCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('samson:release:prepare')->setAliases(array('samson:preparerelease'))
            ->addArgument('instance', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'The parameters.yml file to use')
            ->addOption('target', 't', InputOption::VALUE_OPTIONAL, 'Where should we put the prepared source?')
            ->addOption('skip-vendors', null, InputOption::VALUE_NONE, 'Don\'t include vendors')
            ->addOption('skip-vendors', null, InputOption::VALUE_NONE, 'Don\'t include vendors')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignore git errors')
        ;
    }

    private function determineDefaultSource($tag)
    {
        $dir = sys_get_temp_dir();
        $appData = $this->getContainer()->getParameter('samson_core.app');

        return $dir.DIRECTORY_SEPARATOR.preg_replace('/\W/', '_', $appData['name']).'_'.$tag;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $determiner = $this->getContainer()->get('samson_release.version_determiner');

        if (!$input->getOption('force')) {
            $builder = new \Symfony\Component\Process\ProcessBuilder(array('git', 'status', '-s'));
            $process = $builder->getProcess();
            $process->run();

            if (strlen($process->getOutput())) {
                throw new \RuntimeException('The working tree has uncommitted changes!');
            }

            $tag = $determiner->getCurrentTag();

            file_put_contents($this->getContainer()->getParameter('kernel.root_dir').'/version.txt', $tag);
        } else {
            $tag = $determiner->determineVersion();
        }

        $dialog = $this->getHelperSet()->get('dialog');

        if (null === ($target = $input->getOption('target'))) {
            $target = $dialog->ask($output, 'Where should we put the prepared source? ['.$this->determineDefaultSource($tag).'.tar.gz]', $this->determineDefaultSource($tag).'.tar.gz');
        }

        $prepareRelease = $this->getContainer()->get('samson_release.prepare_release');
        $prepareRelease->setOutput($output);
        $prepareRelease->preparerelease($this->getContainer()->getParameter('kernel.root_dir').'/..', $target, $input->getArgument('instance'));

        $fs = new Filesystem;
        $fs->remove($this->getContainer()->getParameter('kernel.root_dir').'/version.txt');
    }
}