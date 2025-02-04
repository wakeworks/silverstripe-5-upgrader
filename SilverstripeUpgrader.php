<?php

namespace WakeWorks\SilverstripeFiveUpgrader\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Composer\Console\Application as ComposerApplication;
use Symfony\Component\Console\Input\ArrayInput;
use DotEnvWriter\DotEnvWriter;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Process\Process;

/**
 * Command to try to update a composer file to use SS4.
 */
class SilverstripeUpgrader extends Command
{
    protected function configure(): void
    {
        $this->setName('upgrade')
            ->setDescription('Tries to upgrade a Silverstripe project from 4 to 5.')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to your Silverstripe project.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = realpath($input->getArgument('path'));
        if($path === false) {
            throw new \Exception('Path does not exist');
        }
        $io = new SymfonyStyle($input, $output);

        $io->note('Updating composer.json');
        $this->upgradeComposerJson($path, $io);
        $io->note('Updating dependencies');
        $this->doComposerUpdate($path, $io);
        $io->note('Exposing dependencies');
        $this->doComposerVendorExpose($path, $io);
        $io->note('Updating .env file');
        $this->updateEnvFile($path, $io);
        $io->note('Updating php files in app/src using rector');
        $this->doRectorUpgrade($path, $io);

        return 0;
    }

    private function getSrcPath($path): string {
        $filePath = join('/', [$path, 'app', 'src']);
        if(!realpath($filePath)) {
            throw new \Exception('Invalid path to app/src');
        }

        return $filePath;
    }

    private function doRectorUpgrade($path, $io) {
        $srcPath = $this->getSrcPath($path);
        $process = new Process([
            PHP_BINARY,
            'vendor/bin/rector',
            'process',
            $srcPath,
            '--clear-cache',
            '--no-progress-bar'
        ]);
        $process->run(function ($type, $buffer) use ($io) {
            $io->text((string) $buffer);
        });

        if(!$process->isSuccessful()) {
            throw new \Exception('Unable to run rector upgrade');
        }
    }

    private function getComposerJsonFilePath($path): string {
        $filePath = join('/', [$path, 'composer.json']);
        if(!realpath($filePath)) {
            throw new \Exception('Invalid path to composer.json file');
        }

        return $filePath;
    }

    private function removeComposerLockFile($path): void {
        $filePath = join('/', [$path, 'composer.lock']);
        if(file_exists($filePath)) {
            unlink($filePath);
        }
    }

    private function backupComposerJson($path): string {
        $tempfile = $this->getComposerJsonFilePath($path) . '.upgradebak';
        $currentContent = file_get_contents($this->getComposerJsonFilePath($path));
        file_put_contents(
            $tempfile,
            $currentContent
        );
        return $tempfile;
    }

    private array $replace_package_map = [
        'undefinedoffset/sortablegridfield' => 'symbiote/silverstripe-gridfieldextensions',
        'php' => null,
        'silverstripe/recipe-core' => null,
        'heyday/silverstripe-responsive-images' => 'wakeworks/silverstripe-responsive-images'
    ];

    private array $package_version_constraints = [
        'colymba/gridfield-bulk-editing-tools' => '^4'
    ];

    private function replaceUnsupportedPackages(array $packages): array {
        $newPackages = [];
        foreach($packages as $package => $version) {
            if(array_key_exists($package, $this->replace_package_map)) {
                if($this->replace_package_map[$package] !== null) {
                    $newPackages[$this->replace_package_map[$package]] = null;
                }
            } else {
                $newPackages[$package] = $version;
            }
        }

        return $newPackages;
    }

    private function upgradeComposerJson($path, $io): void {
        $composerJson = json_decode(
            file_get_contents($this->getComposerJsonFilePath($path)),
            true
        );
        if(!$composerJson) {
            throw new \Exception('composer.json file is invalid');
        }
        $backupFilePath = $this->backupComposerJson($path);
        $this->removeComposerLockFile($path);

        $packages = $composerJson['require'] ?? [];
        $packages = $this->replaceUnsupportedPackages($packages);
        $devPackages = $composerJson['require-dev'] ?? [];
        $devPackages = $this->replaceUnsupportedPackages($devPackages);

        $composerJson['require'] = [
            'php' => '^8.1',
            'silverstripe/recipe-core' => '^5'
        ];

        file_put_contents(
            $this->getComposerJsonFilePath($path),
            json_encode($composerJson, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        foreach([false => $packages, true => $devPackages] as $isDev => $packages) {
            foreach($packages as $package => $version) {
                // First try if current version is still installable
                if($this->doComposerRequire($path, $package, $version, $isDev, quiet: true)) {
                    continue;
                }
                // If it does not work, try with new / without constraint
                $version = $this->package_version_constraints[$package] ?? null;
                $this->removeComposerLockFile($path);
                if(!$this->doComposerRequire($path, $package, $version, $isDev, quiet: true)) {
                    file_put_contents(
                        $this->getComposerJsonFilePath($path),
                        file_get_contents($backupFilePath)
                    );
                    $this->removeComposerLockFile($path);
                    throw new \Exception('Unable to require package ' . $package);
                }
            }
        }
    }

    private function doComposerRequire($path, $package, $version = null, $isDev = false, $quiet = false): bool {
        if($version) {
            $package .= ' ' . $version;
        }
        $options = [
            'command' => 'require',
            'packages' => [$package],
            '--working-dir' => $path,
            '--no-install' => true
        ];
        if($isDev) {
            $options['--dev'] = true;
        }
        $input = new ArrayInput($options);
        $application = new ComposerApplication();
        $application->setAutoExit(false);
        $exitCode = $application->run($input, $quiet ? new NullOutput() : null);
        if($exitCode !== 0) {
            return false;
        }
        return true;
    }

    private function doComposerUpdate($path, $io) {
        $input = new ArrayInput(['command' => 'update', '--working-dir' => $path]);
        $application = new ComposerApplication();
        $application->setAutoExit(false);
        $exitCode = $application->run($input);
        
        if($exitCode !== 0) {
            throw new \Exception('Unable to do composer update');
            return;
        }
    }

    private function doComposerVendorExpose($path, $io) {
        $input = new ArrayInput(['command' => 'vendor-expose', '--working-dir' => $path]);
        $application = new ComposerApplication();
        $application->setAutoExit(false);
        $exitCode = $application->run($input);
        
        if($exitCode !== 0) {
            throw new \Exception('Unable to do composer vendor-expose');
            return;
        }
    }

    private function updateEnvFile($path, $io) {
        $envFile = join('/', [$path, '.env']);
        if(!realpath($envFile)) {
            $io->text('No .env file found to make updates to');
        }

        $env = new DotEnvWriter($envFile);
        $dbClass = $env->get('SS_DATABASE_CLASS');
        if($dbClass && $dbClass['value'] === 'MySQLPDODatabase') {
            $io->note('Changing removed SS_DATABASE_CLASS=MySQLPDODatabase to SS_DATABASE_CLASS=MySQLDatabase');
            $env->set('SS_DATABASE_CLASS', 'MySQLDatabase');
            $env->save();
        }
    }
}
