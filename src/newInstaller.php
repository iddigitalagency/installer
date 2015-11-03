<?php

namespace Iddigital\Installer\Console;

use ZipArchive;
use RuntimeException;
use GuzzleHttp\Client;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command; //https://packagist.org/packages/symfony/console
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NewInstaller extends Command
{

    protected $zipurl = 'https://github.com/jaiwalker/mailtrap/archive/master.zip';

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('install')
            ->setDescription('Downloading and installing new ')
            ->addArgument('name', InputArgument::REQUIRED);
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->verifyApplicationDoesntExist(
            $directory = getcwd().'/'.$input->getArgument('name'),
            $output
        );

        $output->writeln('<info>starting the Process</info>');

        $this->download($zipFile = $this->makeFilename())
             ->extract($zipFile, $directory)
             ->cleanUp($zipFile);

        $output->writeln('<comment>Downloaded zip file and extracted it .</comment>');
        // If composer  run composer commands
        $composer = $this->findComposer();

        $commands = [ $composer.' run-script post-root-package-install'];
       // Using Symphony Process //https://packagist.org/packages/symfony/process
        $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        $output->writeln('<comment>All completed.</comment>');
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory, OutputInterface $output)
    {
        if (is_dir($directory)) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd().'/iddigital'.md5(time().uniqid()).'.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function download($zipFile)
    {
        $response = (new Client)->get($this->zipurl);

        file_put_contents($zipFile, $response->getBody());

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    protected function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;

        $archive->open($zipFile);

        $archive->extractTo($directory);

        $archive->close();

        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);

        @unlink($zipFile);

        return $this;
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" composer.phar';
        }

        return 'composer';
    }
}
