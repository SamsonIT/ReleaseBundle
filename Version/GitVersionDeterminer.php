<?php

namespace Samson\Bundle\ReleaseBundle\Version;

class GitVersionDeterminer implements VersionDeterminerInterface
{
    private $git;

    public function __construct()
    {
        $ef = new \Symfony\Component\Process\ExecutableFinder;
        $this->git = $ef->find('git', null, array('/usr/bin'));
    }

    public function determineVersion()
    {

        $tag = $this->getCurrentTag();
        if (preg_match('/^\d+\.\d+\.\d+$/', $tag)) {
            return $tag;
        }

        $currentBranch = $this->getCurrentBranch();

        if (!preg_match('/^\d+\.\d+$/', $currentBranch)) {
            return 'dev-'.$currentBranch;
        }

        $latestTag = $this->getLatestTag();

        if (0 !== strpos($latestTag, $currentBranch)) {
            return $currentBranch.'.0-dev';
        }
        $tagParts = explode(".", $latestTag);
        $tagParts[count($tagParts) - 1]++;
        return implode(".", $tagParts).'-dev';
    }

    public function getCurrentTag()
    {
        $builder = new \Symfony\Component\Process\ProcessBuilder(array($this->git, 'tag', '--contains', 'HEAD'));
        $process = $builder->getProcess();
        $process->run();

        $tag = trim($process->getOutput());

        if (!strlen($tag)) {
            return null;
        }
        
        return $tag;
    }

    private function getLatestTag()
    {
        $p = \Symfony\Component\Process\ProcessBuilder::create(array($this->git, 'describe', '--abbrev=0', '--tags'))->getProcess();
        $p->run();
        $latestTag = trim($p->getOutput());

        if (!strlen($latestTag)) {
            return null;
        }
        
        return $latestTag;
    }

    private function getCurrentBranch()
    {
        $p = \Symfony\Component\Process\ProcessBuilder::create(array($this->git, 'rev-parse', '--abbrev-ref', 'HEAD'))->getProcess();
        $p->run();
        return trim($p->getOutput());
    }
}