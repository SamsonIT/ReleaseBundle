<?php

namespace Samson\Bundle\ReleaseBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
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
        $this->addOption('tag', null, InputOption::VALUE_NONE, 'Actually create the tag');
        $this->addArgument('level', InputArgument::OPTIONAL, 'What level of version should we create?', 'micro');
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

        $nextVersionParts = explode(".", $m[1]);
        switch($input->getArgument('level')) {
            case 'micro':
                break;
            case 'minor':
                $nextVersionParts[1]++;
                $nextVersionParts[2] = 0;
                break;
            case 'major':
                if ($this->getCurrentBranch() != 'master') {
                    throw new \InvalidArgumentException('Cannot create new major version from version branch!');
                }

                $nextVersionParts[0]++;
                $nextVersionParts[1] = 0;
                $nextVersionParts[2] = 0;
                break;
            default:
                throw new \InvalidArgumentException('invalid level '.$input->getArgument('level').'. Should be one of major, minor of micro (default)');
        }
        $nextVersion = implode(".", $nextVersionParts);

        if ($input->getOption('tag')) {
            /* @var $helper \Symfony\Component\Console\Helper\DialogHelper */
            $helper = $this->getHelper('dialog');
            $default = 'Version '.$nextVersion;
            $message = $helper->ask($output, 'Message for the annotated tag ['.$default.'] ', $default);

            /* @var $p \Symfony\Component\Process\Process */
            $p = \Symfony\Component\Process\ProcessBuilder::create(array($this->getGit(), 'tag', '-am', '"'.addslashes($message).'"', $nextVersion))->getProcess();
            $p->run();

            if ($p->getExitCode()) {
                $output->write($p->getErrorOutput());
                throw new \RuntimeException('Git gave an error!');
            }
        } else {
            $output->writeln('Not actually tagging version '.$nextVersion.' (use --tag to actually tag)');
        }
    }

    private function getGit()
    {
        $ef = new \Symfony\Component\Process\ExecutableFinder;
        return $ef->find('git', null, array('/usr/bin'));
    }

    private function getCurrentBranch()
    {
        $p = \Symfony\Component\Process\ProcessBuilder::create(array($this->getGit(), 'rev-parse', '--abbrev-ref', 'HEAD'))->getProcess();
        $p->run();
        return trim($p->getOutput());
    }
}