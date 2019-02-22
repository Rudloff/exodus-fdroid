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
     * @var SymfonyStyle
     */
    private $io;

    /**
     * Current number of downloaded bytes.
     * @var int
     */
    private $downloadedBytes;

    /**
     * Add command arguments.
     * @return void
     */
    protected function configure()
    {
        $this->setName('scan')
            ->setDescription('Scan an APK')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'App ID'
            );
    }

    /**
     * Display a progress bar when downloading a file.
     * @param int $downloadTotal   Total number of bytes to download
     * @param int $downloadedBytes Number of bytes already downloaded
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
     * @return void
     */
    private function finishDownload()
    {
        unset($this->downloadedBytes);
        $this->io->progressFinish();
    }

    /**
     * Execute the command.
     * @param  InputInterface  $input  Input
     * @param  OutputInterface $output Output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $client = new Client(['progress' => [$this, 'displayProgress']]);

        $tmpRoot = sys_get_temp_dir().'/fdroid/';
        if (!is_dir($tmpRoot)) {
            mkdir($tmpRoot);
        }

        $indexPath = $tmpRoot.'index.xml';

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

        $app = $fdroid->getAppById($input->getArgument('id'));
        if (!isset($app->package)) {
            $this->io->error('Could not find this app.');

            return;
        }

        if (is_array($app->package)) {
            $apkName = $app->package[0]->apkname;
        } else {
            $apkName = $app->package->apkname;
        }

        $apkPath = $tmpRoot.$apkName;

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

        $process = new Process(
            [
                'python3',
                __DIR__.'/../vendor/exodus-privacy/exodus-standalone/exodus_analyze.py',
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
        $processOutput = $process->getOutput();
        if (empty($processOutput)) {
            $this->io->error($process->getErrorOutput());
        } else {
            $this->io->block($processOutput);
        }
    }
}
