<?php

declare(strict_types=1);

namespace App\Command;

use App\Utils\Validator;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Mael\InterventionImageBundle\MaelInterventionImageManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Stopwatch\Stopwatch;

final class GenerateThumbnailCommand extends Command
{
    const FILE_TYPE = 'file';
    const DIRECTORY_TYPE = 'dir';
    const STORAGE_LOCAL_TYPE = 'local';
    const STORAGE_AMAZON_S3_TYPE = 'aws';
    const STORAGE_DROPBOX_TYPE = 'dropbox';

    protected static $defaultName = 'generate-thumbnail';

    /**
     * @var SymfonyStyle
     */
    private $io;

    private $validator;
    private $image;
    private $localStorage;
    private $awsStorage;
    private $dropboxStorage;

    public function __construct(
        Validator $validator,
        MaelInterventionImageManager $image,
        FilesystemOperator $localStorage,
        FilesystemOperator $awsStorage,
        FilesystemOperator $dropboxStorage
    ) {
        parent::__construct();

        $this->validator = $validator;
        $this->image = $image;
        $this->localStorage = $localStorage;
        $this->awsStorage = $awsStorage;
        $this->dropboxStorage = $dropboxStorage;
    }

    protected function configure()
    {
        $this
            ->setDescription('Generates thumbnail images')
            ->setHelp($this->getCommandHelp())
            ->addArgument('path', InputArgument::REQUIRED, 'The path of image/directory')
            ->addOption('type', 't',InputOption::VALUE_REQUIRED, 'The path type')
            ->addOption('storage', 's', InputOption::VALUE_OPTIONAL, 'The destination where images will be stored', self::STORAGE_LOCAL_TYPE)
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (null !== $input->getArgument('path') && null !== $input->getOption('type')) {
            return;
        }

        $this->io->title('Generate Thumbnail Command Interactive Wizard');
        $this->io->text([
            'If you prefer to not use this interactive wizard, provide the',
            'arguments required by this command as follows:',
            '',
            ' $ php bin/console generate-thumbnail path --type=path_type',
            '',
            'No we\'ll ask you for the value of missing command arguments.'
        ]);

        // Ask for the path if it's not defined
        $path = $input->getArgument('path');
        if (null !== $path) {
            $this->io->text(' > <info>Path</info>: ' . $path);
        } else {
            $path = $this->io->ask('Path', null, [$this->validator, 'validatePath']);
            $input->setArgument('path', $path);
        }

        // Ask for the path type if it's not defined
        $type = $input->getOption('type');
        if (null !== $type) {
            $this->io->text('> <info>Type</info>:' . $type);
        } else {
            $type = $this->io->ask('Type', null, [$this->validator, 'validateType']);
            $input->setOption('type', $type);
        }

    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('generate-thumbnail-command');

        $path = $input->getArgument('path');
        $type = $input->getOption('type');
        $storage = $input->getOption('storage');

        $this->validateData($path, $type, $storage);

        if (self::DIRECTORY_TYPE === $type) {
            $finder = new Finder();
            $files = $finder->in($path)->files()->name(['*.jpg', '*.jpeg', '*.png']);
        } else {
            $files = [new \SplFileInfo($path)];
        }

        $total = count($files);
        $fail = 0;

        $progress = $this->io->createProgressBar($total);
        $progress->start();

        foreach ($files as $file) {
            $progress->setMessage(sprintf(' Processing image <info>%s</info>...', $file->getBasename()));
            $image = $this->image->make($file);
            $image->resize(150, 150, function ($constraint) {
                $constraint->aspectRatio();
            });

            try {
                switch ($storage) {
                    case self::STORAGE_LOCAL_TYPE:
                        $this->localStorage->write($file->getBasename(), $image->stream()->getContents());
                        break;
                    case self::STORAGE_AMAZON_S3_TYPE:
                        $this->awsStorage->write($file->getBasename(), $image->stream()->getContents());
                        break;
                    case self::STORAGE_DROPBOX_TYPE:
                        $this->dropboxStorage->write($file->getBasename(), $image->stream()->getContents());
                }
            } catch (FilesystemException $e) {
                $this->io->writeln(sprintf(' Failed to process <error>%s</error>.', $file->getBasename()));
                $this->io->writeln($e->getMessage());
                $fail++;
            }

            $progress->advance();
        }

        $progress->clear();
        $this->io->writeln('Command completed successfully.');
        $this->io->writeln(sprintf('Success: %d, Fail: %d', $total - $fail, $fail));

        $event = $stopwatch->stop('generate-thumbnail-command');

        if ($output->isVerbose()) {
            $this->io->comment(sprintf('Elapsed time: %.2f ms / Consumed memory: %.2f MB', $event->getDuration(), $event->getMemory() / (1024 ** 2)));
        }

        return Command::SUCCESS;
    }

    private function validateData($path, $type, $storage)
    {
        $this->validator->validatePath($path);
        $this->validator->validateType($type);
        $this->validator->validateStorage($storage);
    }

    private function getCommandHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> generates thumbnails from selected images.

    <info>php %command.full_name%</info> image.png --type=file

This will generate thumbnail for selected image and save it to images directory.
To generate thumbnails for multiple images located in directory, specify path type:

    <info>php %command.full_name%</info> /path/to/images <comment>--type=dir</comment>

To change output destination, specify storage type:

    <info>php %command.full_name%</info> image.png --type=file <comment>--storage=dropbox</comment>

If you omit any of the two required arguments, the command will ask you to
provide the missing values:

    # command will ask you for path and path type
    <info>php %command.full_name%</info>

    # command will ask you for path type
    <info>php %command.full_name%</info> <comment>image.png</comment>
HELP;
    }
}