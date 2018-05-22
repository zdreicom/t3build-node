<?php
declare(strict_types=1);

namespace Z3\T3buildNode\Service\Path;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Z3\T3build\Service\Bootstrap;
use Z3\T3build\Service\Config;

class NodePaths
{
    /**
     * @var string $nodeRootDirectory = '';
     */
    private static $nodeRootDirectory = '';

    /**
     * @var string $nodeBinDirectory = '';
     */
    private static $nodeBinDirectory = '';

    /**
     * @var string $nodeExecutableDirectory = '';
     */
    private static $nodeExecutableDirectory = '';

    /**
     * @var string $nodeExecutable
     */
    private static $nodeExecutable = '';

    /**
     * @var string
     */
    private static $nodeVersion = '6.10.3';

    /**
     * @var string $nmExecutable
     */
    private static $npmExecutable = '';

    public static function initNode()
    {
        $nodeExecutablesDirectory = 'node-v' . self::$nodeVersion;

        self::$nodeRootDirectory = Config::getPaths()->getT3buildVendorDirectory() . '/z3/t3build-node/libraries/node';
        if (Bootstrap::getRootPackage()->getName() === 'z3/t3build-node') {
            self::$nodeRootDirectory = Config::getPaths()->getProjectRootDirectory() . '/libraries/node';
        }
        self::$nodeBinDirectory = Config::getPaths()->getT3BuildTemporaryDirectory() . '/node_modules/.bin';

        self::$nodeExecutableDirectory = self::$nodeRootDirectory . '/' . $nodeExecutablesDirectory . '/bin';
        self::$nodeExecutable = self::$nodeExecutableDirectory . '/node';
        self::$npmExecutable = self::$nodeExecutableDirectory . '/npm';

        $setSymlink = true;
        switch (PHP_OS) {
            case 'Darwin':
                $nodeExecutableSystemSpecific = 'node-darwin-x64';
                break;
            case 'Linux':
                switch (self::guessLinuxType()) {
                    case 'x86_64':
                        $nodeExecutableSystemSpecific = 'node-linux-x64';
                        break;
                    default:
                        self::$nodeExecutable = trim((new Process('which node'))->mustRun()->getOutput());
                        self::$npmExecutable = trim((new Process('which npm'))->mustRun()->getOutput());
                        $setSymlink = false;
                }
                break;
            default:
                throw new \Exception('Unsupported operation system: ' . PHP_OS);

        }

        // HOTFIX use local node version

        self::$nodeExecutable = trim((new Process('which node'))->mustRun()->getOutput());
        self::$npmExecutable = trim((new Process('which npm'))->mustRun()->getOutput());
        $setSymlink = false;

        if ($setSymlink && !file_exists(self::$nodeExecutable)) {
            $createSymLink = 'ln -s ' . $nodeExecutableSystemSpecific . ' node';
            $process = new Process($createSymLink, self::$nodeExecutableDirectory);
            $process->mustRun();
        }

        if ($setSymlink && !is_file(self::$npmExecutable)) {
            $process = new Process('chmod +x node-darwin-x64 node-linux-x64', self::$nodeExecutableDirectory);
            $process->mustRun();
            $createSymLink = 'ln -s ' . self::$nodeRootDirectory . '/' . $nodeExecutablesDirectory . '/lib/node_modules/npm/bin/npm-cli.js npm';
            $process = new Process($createSymLink, self::$nodeExecutableDirectory);
            $process->mustRun();
        }

        if (!is_file(self::$nodeExecutable) || !is_file(self::$npmExecutable)) {
            throw new \Exception('Path to node or npm is invalid. node: ' . escapeshellarg(self::$nodeExecutable)
                . ' npm: '
                . escapeshellarg(self::$npmExecutable));
        }

        if (!is_dir(self::getNodeBinDirectory())) {
            self::loadPackages();
        }
    }

    /**
     * @return string
     */
    private static function guessLinuxType(): string
    {
        ob_start();
        phpinfo();
        $phpinfo = ob_get_contents();
        ob_get_clean();

        if (strpos($phpinfo, 'x86_64-alpine-linux-musl') !== false) {
            return 'x86_64-linux-musl';
        }
        if (strpos($phpinfo, 'x86_64') !== false) {
            return 'x86_64';
        }
        if (strpos($phpinfo, 'amd64')) {
            return ' x86_64';
        }
        return 'Unknown';
    }

    /**
     * @return string
     */
    private static function loadPackages(): string
    {
        $temp = Config::getPaths()->getT3BuildTemporaryDirectory();
        $fileSystem = new Filesystem();
        $fileSystem->copy(self::getNodeRootDirectory() . '/package.json', $temp . '/package.json');
        $command = self::getNodeExecutable() . ' ' . self::getNpmExecutable() . ' install';
        $process = new Process($command, $temp, null, null, 600);
        $process->mustRun();
        return $process->getOutput();
    }

    /**
     * @return string
     */
    public static function getNodeRootDirectory(): string
    {
        return self::$nodeRootDirectory;
    }

    /**
     * @return string
     */
    public static function getNodeBinDirectory(): string
    {
        return self::$nodeBinDirectory;
    }

    /**
     * @return string
     */
    public static function getNodeExecutable(): string
    {
        return self::$nodeExecutable;
    }

    /**
     * @return string
     */
    public static function getNpmExecutable(): string
    {
        return self::$npmExecutable;
    }
}
