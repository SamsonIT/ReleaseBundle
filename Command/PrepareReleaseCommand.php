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
            ->setName('samson:preparerelease')
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
        if (!$input->getOption('force')) {
            $builder = new \Symfony\Component\Process\ProcessBuilder(array('git', 'status', '-s'));
            $process = $builder->getProcess();
            $process->run();

            if (strlen($process->getOutput())) {
                throw new \RuntimeException('The working tree has uncommitted changes!');
            }

            $tag = $this->getTag();
            file_put_contents($this->getContainer()->getParameter('kernel.root_dir').'/version.txt', $tag);
        } else {
            $tag = 'dev';
        }

        $dialog = $this->getHelperSet()->get('dialog');

        if (null === ($target = $input->getOption('target'))) {
            $target = $dialog->ask($output, 'Where should we put the prepared source? ['.$this->determineDefaultSource($tag).'.tar.gz]', $this->determineDefaultSource($tag).'.tar.gz');
        }

        $prepareRelease = $this->getContainer()->get('samson_release.prepare_release');
        $prepareRelease->setOutput($output);
        $prepareRelease->preparerelease($this->getContainer()->getParameter('kernel.root_dir').'/..', $target);

        $fs = new Filesystem;
        $fs->remove($this->getContainer()->getParameter('kernel.root_dir').'/version.txt');
    }

    private function getTag()
    {
        $builder = new \Symfony\Component\Process\ProcessBuilder(array('git', 'tag', '--contains', 'HEAD'));
        $process = $builder->getProcess();
        $process->run();

        $tag = $process->getOutput();
        if (!strlen($tag)) {
            throw new \RuntimeException('The current commit is not tagged!');
        }
        $tags = preg_split('/\n|\r\n|\r/', trim($tag));

        if (count($tags) > 1) {
            throw new \RuntimeException('The current commit has multiple tags!');
        }
        return $tags[0];
    }
}