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

    public function prepareRelease($source, $target)
    {
        $finder = new Finder();

        $finder->files()
            ->depth('>= 1')
            ->exclude("app/cache")
            ->notPath('app/config/parameters.yml')
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
        } else {
            $builder = new \Symfony\Component\Process\ProcessBuilder(array('tar', 'cvzf', $target, '-T', '-'));
            $process = $builder->getProcess();

            $process->setStdin(implode("\n", array_map(function($file) {
                            return $file->getRelativePathname();
                        }, $files)));
            $process->run();

            if (strlen($process->getErrorOutput())) {
                throw new \RuntimeException('Couldn\'t create tar archive: '.$process->getErrorOutput());
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