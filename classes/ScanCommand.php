<?php
/**
 * ScanCommand class.
 */

namespace ExodusFdroid;

use fdroid;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Command that downloads and scan an APK.
 */
class ScanCommand extends Command
{
    /**
     * CLI input/output wrapper.
     *
     * @var SymfonyStyle
     */
    private $io;

    /**
     * Current number of downloaded bytes.
     *
     * @var int
     */
    private $downloadedBytes;

    /**
     * Name of the temporary folder used to store the index and APK file.
     *
     * @var string
     */
    private $tmpRoot;

    /**
     * ScanCommand constructor.
     *
     * @param string $tmpName Name of the temporary folder
     */
    public function __construct($tmpRoot = null)
    {
        parent::__construct();
        if (isset($tmpRoot)) {
            $this->tmpRoot = $tmpRoot;
        } else {
            $this->tmpRoot = sys_get_temp_dir().'/fdroid/';
        }
    }

    /**
     * Add command arguments.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('exodus-fdroid')
            ->setDescription('Scan an APK')
            ->addUsage('pro.rudloff.openvegemap')
            ->addUsage('--path /path/to/apk')
            ->addArgument(
                'id',
                InputArgument::OPTIONAL,
                'App ID'
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                "Scan a local APK instead (don't download it from f-droid.org)"
            );
    }

    /**
     * Display a progress bar when downloading a file.
     *
     * @param int $downloadTotal   Total number of bytes to download
     * @param int $downloadedBytes Number of bytes already downloaded
     *
     * @return void
     */
    public function displayProgress($downloadTotal, $downloadedBytes)
    {
        if ($downloadTotal > 0) {
            if (!isset($this->downloadedBytes)) {
                $this->io->progressStart($downloadTotal);
            } else {
                $this->io->progressAdvance($downloadedBytes - $this->downloadedBytes);
            }

            $this->downloadedBytes = $downloadedBytes;
        }
    }

    /**
     * Stop updating the progress bar when a downloaded finished.
     *
     * @return void
     */
    private function finishDownload()
    {
        unset($this->downloadedBytes);
        $this->io->progressFinish();
    }

    /**
     * Download an APK from f-droid.org.
     *
     * @param string $appId App ID
     *
     * @return string Path to the downloaded APK
     */
    private function downloadApk($appId)
    {
        $client = new Client(['progress' => [$this, 'displayProgress']]);

        if (!is_dir($this->tmpRoot)) {
            mkdir($this->tmpRoot);
        }

        $indexPath = $this->tmpRoot.'index.xml';

        if (!is_file($indexPath)) {
            $this->io->text('Downloading index file to '.$indexPath);
            $client->request(
                'GET',
                'https://f-droid.org/repo/index.xml',
                [
                    'sink' => $indexPath,
                ]
            );
            $this->finishDownload();
        }

        // The fdroid constructor wants a path.
        $fdroid = new fdroid($indexPath);

        $app = $fdroid->getAppById($appId);
        if (!isset($app->package)) {
            $this->io->error('Could not find this app.');

            return 1;
        }

        if (is_array($app->package)) {
            $apkName = $app->package[0]->apkname;
        } else {
            $apkName = $app->package->apkname;
        }

        $apkPath = $this->tmpRoot.$apkName;

        if (!is_file($apkPath)) {
            $this->io->text('Downloading APK file to '.$apkPath);
            $client->request(
                'GET',
                'https://f-droid.org/repo/'.$apkName,
                [
                    'sink' => $apkPath,
                ]
            );
            $this->finishDownload();
        }

        return $apkPath;
    }

    /**
     * Execute the command.
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $apkPath = $input->getOption('path');
        $appId = $input->getArgument('id');

        if (!isset($apkPath)) {
            if (isset($appId)) {
                $apkPath = $this->downloadApk($appId);
            } else {
                $this->io->error('Please specify an app ID.');

                return 1;
            }
        }

        $process = new Process(
            [
                'python3',
                __DIR__.'/../vendor/exodus-privacy/exodus-standalone/exodus_analyze.py',
                '-j',
                $apkPath,
            ]
        );
        $process->setEnv(
            [
                'PYTHONPATH' => __DIR__.'/../vendor/androguard/androguard/:'.
                    __DIR__.'/../vendor/exodus-privacy/exodus-core/',
            ]
        );
        $process->inheritEnvironmentVariables();
        $process->run();

        $errorOutput = $process->getErrorOutput();

        // exodus-standalone returns the number of trackers in the exit code so isSuccessful() is not reliable.
        if (empty($errorOutput)) {
            $processOutput = $process->getOutput();
            $result = json_decode($processOutput);
            $this->io->title($result->application->name.' ('.$result->application->version_name.')');
            if (empty($result->trackers)) {
                $this->io->success('No trackers found');
            } else {
                $trackers = [];
                foreach ($result->trackers as $tracker) {
                    $trackers[] = $tracker->name;
                }
                $this->io->listing($trackers);
            }

            if ($output->isDebug()) {
                $this->io->section('JSON output');
                $this->io->block($processOutput);
            }
        } else {
            $this->io->error($errorOutput);

            return $process->getExitCode();
        }
    }
}
