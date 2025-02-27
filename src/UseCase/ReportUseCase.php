<?php

declare(strict_types=1);

namespace Siketyan\Loxcan\UseCase;

use Siketyan\Loxcan\Model\DependencyCollectionDiff;
use Siketyan\Loxcan\Reporter\ReporterInterface;

class ReportUseCase
{
    /**
     * @var ReporterInterface[]
     */
    private array $reporters;

    /**
     * @param ReporterInterface[] $reporters
     */
    public function __construct(
        array $reporters
    ) {
        $this->reporters = $reporters;
    }

    /**
     * @param DependencyCollectionDiff[] $diffs
     */
    public function report(array $diffs): void
    {
        foreach ($this->reporters as $reporter) {
            if ($reporter->supports()) {
                $reporter->report($diffs);
            }
        }
    }
}
