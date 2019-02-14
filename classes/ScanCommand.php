<?php

namespace ExodusFdroid;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use GuzzleHttp\Client;
use fdroid;

class ScanCommand extends Command
{

    private $io;
    private $downloadedBytes;

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

    private function finishDownload()
    {
        unset($this->downloadedBytes);
        $this->io->progressFinish();
    }

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
                    'sink' => $indexPath
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
                    'sink' => $apkPath
                ]
            );
            $this->finishDownload();
        }

        $process = new Process(
            [
                'python3',
                __DIR__.'/../vendor/exodus-privacy/exodus-standalone/exodus_analyze.py',
                $apkPath
            ]
        );
        $process->setEnv(
            [
                'PYTHONPATH' => __DIR__.'/../vendor/androguard/androguard/:'.
                    __DIR__.'/../vendor/exodus-privacy/exodus-core/'
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
