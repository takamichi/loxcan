<?php

declare(strict_types=1);

namespace Siketyan\Loxcan\Command;

use Eloquent\Pathogen\Path;
use Siketyan\Loxcan\Model\DependencyCollectionDiff;
use Siketyan\Loxcan\Model\Repository;
use Siketyan\Loxcan\Versioning\VersionDiff;
use Siketyan\Loxcan\UseCase\ReportUseCase;
use Siketyan\Loxcan\UseCase\ScanUseCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ScanCommand extends Command
{
    private const NAME = 'scan';

    private ScanUseCase $useCase;
    private ReportUseCase $reportUseCase;

    public function __construct(
        ScanUseCase $useCase,
        ReportUseCase $reportUseCase
    ) {
        parent::__construct(self::NAME);

        $this->useCase = $useCase;
        $this->reportUseCase = $reportUseCase;
    }

    protected function configure()
    {
        $this
            ->addArgument('base', InputArgument::REQUIRED)
            ->addArgument('head', InputArgument::REQUIRED)
            ->addOption(
                'working-dir',
                'd',
                InputOption::VALUE_OPTIONAL,
                'If specified, use the given directory as working directory.'
            )
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $workingDir = $input->getOption('working-dir');
        if ($workingDir === null) {
            $workingDir = getcwd();
        }

        $repository = new Repository(Path::fromString($workingDir));
        $base = (string) $input->getArgument('base');
        $head = (string) $input->getArgument('head');

        $diffs = $this->useCase->scan($repository, $base, $head);

        if (count($diffs) === 0) {
            $io->writeln(
                '✨ No lock file changes found, looks shine!',
            );
        } else {
            $this->printDiffs($io, $diffs);
        }

        $this->reportUseCase->report($diffs);

        return 0;
    }

    /**
     * @param SymfonyStyle               $io
     * @param DependencyCollectionDiff[] $diffs
     */
    private function printDiffs(SymfonyStyle $io, array $diffs): void
    {
        foreach ($diffs as $file => $diff) {
            $io->section($file);

            if ($diff->count() === 0) {
                $io->writeln(
                    '🔄 The file was updated, but no dependency changes found.',
                );

                continue;
            }

            $rows = [];

            foreach ($diff->getAdded() as $dependency) {
                $rows[] = [
                    '➕',
                    $dependency->getPackage()->getName(),
                    '',
                    $dependency->getVersion(),
                ];
            }

            foreach ($diff->getUpdated() as $dependencyDiff) {
                $versionDiff = $dependencyDiff->getVersionDiff();
                $rows[] = [
                    $this->getVersionDiffTypeEmoji($versionDiff),
                    $dependencyDiff->getPackage()->getName(),
                    $versionDiff->getBefore(),
                    $versionDiff->getAfter(),
                ];
            }

            foreach ($diff->getRemoved() as $dependency) {
                $rows[] = [
                    '➖',
                    $dependency->getPackage()->getName(),
                    $dependency->getVersion(),
                    '',
                ];
            }

            $io->table(
                ['', 'Package', 'Before', 'After'],
                $rows,
            );
        }
    }

    private function getVersionDiffTypeEmoji(VersionDiff $diff): string
    {
        switch ($diff->getType()) {
            case VersionDiff::UPGRADED:
                return '⬆️';

            case VersionDiff::DOWNGRADED:
                return '⬇️';

            default:
            case VersionDiff::UNKNOWN:
                return '🔄';
        }
    }
}
