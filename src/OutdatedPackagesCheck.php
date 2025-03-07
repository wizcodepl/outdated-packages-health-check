<?php

namespace Wizcode\OutdatedPackagesHealthCheck;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Symfony\Component\Process\Process;

class OutdatedPackagesCheck extends Check
{
    protected string $composerPath = 'composer';

    protected bool $direct = false;

    protected bool $includeDev = false;

    public function direct(bool $direct = true): self
    {
        $this->direct = $direct;

        return $this;
    }

    public function includeDev(bool $includeDev = true): self
    {
        $this->includeDev = $includeDev;

        return $this;
    }

    public function composerPath(string $path): self
    {
        $this->composerPath = $path;

        return $this;
    }

    public function run(): Result
    {
        $result = Result::make();

        $command = [$this->composerPath, 'outdated', '--format=json'];

        if ($this->direct) {
            $command[] = '--direct';
        }

        if (! $this->includeDev) {
            $command[] = '--no-dev';
        }

        $process = new Process($command, base_path());
        $process->run();

        if (! $process->isSuccessful()) {
            return $result->failed('Failed: '.$process->getErrorOutput());
        }

        $output = json_decode($process->getOutput(), true);
        $outdatedCount = count($output['installed'] ?? []);

        if ($outdatedCount > 0) {
            return $result
                ->warning(sprintf('%d outdated', $outdatedCount))
                ->shortSummary(sprintf('%d outdated', $outdatedCount))
                ->meta(['outdated_packages' => array_column($output['installed'], 'name')]);
        }

        return $result->ok('All packages are up to date.');
    }
}
