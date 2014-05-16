<?php

namespace Samson\Bundle\ReleaseBundle\PrepareRelease;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * @author Bart van den Burg <bart@samson-it.nl>
 */
class PrepareRelease
{
    private $excluders = array();

    private $output;

    public function addExcluder(ExcluderInterface $excluder)
    {
        $this->excluders[] = $excluder;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function prepareRelease($source, $target, $instance)
    {
        $finder = new Finder();

        $finder->files()
            ->depth('>= 1')
            ->exclude("app/cache")
            ->notPath('app/config/parameters.yml')
            ->exclude('app/config/instance')
            ->exclude('reports')
            ->exclude(".settings")
            ->exclude("app/logs")
            ->exclude("web/bundles")
            ->exclude("web/css")
            ->exclude("web/js")
            ->exclude("bin")
            ->notPath("/vendor\/.*?\/manual\//")
            ->notPath("/vendor\/.*?\/doc\//")
            ->notPath("/vendor\/.*?\/Tests\//")
            ->notPath("/vendor\/.*?\/tests\//")
            ->notPath("/vendor\/.*?\/test-suite\//")
            ->exclude("nbproject")
            ->exclude("builds")
            ->notName("app_dev.php")
            ->notName(".buildpath")
            ->notName(".project")
            ->notName(".gitignore")
        ;

        foreach ($this->excluders as $excluder) {
            $this->output->writeln('Applying excluder '.get_class($excluder));
            $excluder->process($finder);
        }
        if (!count($this->excluders)) {
            $this->output->writeln('No excluders found');
        }

        $finder->in($source);

        $this->output->writeln('Counting files...');
        $files = iterator_to_array($finder);
        $this->output->writeln('Found '.count($files).' files.');

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // windows does not like TAR. Copy files manually.
            foreach ($finder as $file) {
                $this->copyfile($file->getRelativePathname(), $target.'/'.$file->getRelativePathname());
            }
            if (null !== $instance) {
                $instanceFile = "app/config/instance/".$instance;
                $this->output->writeln('Adding instance parameters.yml file: '.$instanceFile);
                $this->copyfile($instanceFile, $target.'/app/config/parameters.yml');
            }
        } else {
            $targetTar = preg_replace('/\.gz$/', '', $target);

            $this->output->writeln('Creating tar archive');
            $builder = new \Symfony\Component\Process\ProcessBuilder(array('tar', 'cf', $targetTar, '-T', '-'));
            $process = $builder->getProcess();

            $files = implode("\n", array_map(function($file) {
                return $file->getRelativePathname();
            }, $files));

            $process->setStdin($files);
            $process->run();

            if (strlen($process->getErrorOutput())) {
                throw new \RuntimeException('Couldn\'t create tar archive: '.$process->getErrorOutput());
            }

            if (null !== $instance) {
                $instanceFile = "app/config/instance/".$instance;
                $this->output->writeln('Adding instance parameters.yml file: '.$instanceFile);
                $parts = array('tar', 'rf', $targetTar, $instanceFile);
                $builder = new \Symfony\Component\Process\ProcessBuilder($parts);
                $process = $builder->getProcess();
                $process->setCommandLine($process->getCommandLine()." --transform='s,".preg_quote($instanceFile, ",").",app/config/parameters.yml,'");
                $process->run();

                if (strlen($process->getErrorOutput())) {
                    throw new \RuntimeException('Couldn\'t append parameters.yml: '.$process->getErrorOutput());
                }
            }

            $this->output->writeln('Creating tar.gz archive');
            $builder = new \Symfony\Component\Process\ProcessBuilder(array('gzip', '-f', $targetTar));

            $process = $builder->getProcess();
            $process->run();

            if (strlen($process->getErrorOutput())) {
                throw new \RuntimeException('Couldn\'t create tar.gz file: '.$process->getErrorOutput());
            }
        }
    }

    private function copyfile($from, $to)
    {
        // make sure all dirs exist

        $to = str_replace('\\', '/', $to);
        $parts = explode('/', $to);
        $path = '';
        // pop off the filename
        array_pop($parts);
        foreach ($parts as $part) {
            if ($path == '') {
                $path .= $part;
            } else {
                $path .= '/'.$part;
            }
            if (!is_dir($path)) {
                mkdir($path);
            }
        }
        copy($from, $to);
    }
}
