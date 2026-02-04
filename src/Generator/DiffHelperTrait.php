<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\DiffOnlyOutputBuilder;

trait DiffHelperTrait
{
    /**
     * @var \PhpCollective\Dto\Generator\IoInterface
     */
    protected IoInterface $io;

    /**
     * @param string $oldContent
     * @param string $newContent
     *
     * @return void
     */
    protected function displayDiff(string $oldContent, string $newContent): void
    {
        $differ = new Differ(new DiffOnlyOutputBuilder());
        $array = $differ->diffToArray($oldContent, $newContent);

        $begin = null;
        $end = null;
        /**
         * @var int $key
         */
        foreach ($array as $key => $row) {
            if ($row[1] === 0) {
                continue;
            }

            if ($begin === null) {
                $begin = $key;
            }
            $end = $key;
        }
        if ($begin === null) {
            return;
        }
        $firstLineOfOutput = $begin > 0 ? $begin - 1 : 0;
        $lastLineOfOutput = count($array) - 1 > $end ? $end + 1 : $end;

        for ($i = $firstLineOfOutput; $i <= $lastLineOfOutput; $i++) {
            $row = $array[$i];

            $char = ' ';
            $output = trim($row[0], "\n\r\0\x0B");

            if ($row[1] === 1) {
                $char = '+';
                $this->io->out('   | ' . $char . $output, 1, IoInterface::VERBOSE);
            } elseif ($row[1] === 2) {
                $char = '-';
                $this->io->out('   | ' . $char . $output, 1, IoInterface::VERBOSE);
            } else {
                $this->io->out('   | ' . $char . $output, 1, IoInterface::VERBOSE);
            }
        }
    }
}
