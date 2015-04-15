<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Diagnostics\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResult;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResultItem;
use Piwik\Plugins\Diagnostics\DiagnosticService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run the diagnostics.
 */
class Run extends ConsoleCommand
{
    /**
     * @var DiagnosticService
     */
    private $diagnosticService;

    public function __construct()
    {
        // Replace this with dependency injection once available
        $this->diagnosticService = StaticContainer::get('Piwik\Plugins\Diagnostics\DiagnosticService');

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('diagnostics:run')
            ->setDescription('Run diagnostics to check that Piwik is installed and runs correctly')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Show all diagnostics, including those that passed with success');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $showAll = $input->getOption('all');

        $report = $this->diagnosticService->runDiagnostics();

        foreach ($report->getAllResults() as $result) {
            $items = $result->getItems();

            if (! $showAll && ($result->getStatus() === DiagnosticResult::STATUS_OK)) {
                continue;
            }

            if (count($items) === 1) {
                $output->writeln($result->getLabel() . ': ' . $this->formatItem($items[0]), OutputInterface::OUTPUT_PLAIN);
                continue;
            }

            $output->writeln($result->getLabel() . ':');
            foreach ($items as $item) {
                $output->writeln("\t- " . $this->formatItem($item), OutputInterface::OUTPUT_PLAIN);
            }
        }

        if ($report->hasWarnings()) {
            $output->writeln(sprintf('<comment>%d warnings detected</comment>', $report->getWarningCount()));
        }
        if ($report->hasErrors()) {
            $output->writeln(sprintf('<error>%d errors detected</error>', $report->getErrorCount()));
            return 1;
        }

        return 0;
    }

    private function formatItem(DiagnosticResultItem $item)
    {
        if ($item->getStatus() === DiagnosticResult::STATUS_ERROR) {
            $tag = 'error';
        } elseif ($item->getStatus() === DiagnosticResult::STATUS_WARNING) {
            $tag = 'comment';
        } else {
            $tag = 'info';
        }

        return sprintf(
            '<%s>%s %s</%s>',
            $tag,
            strtoupper($item->getStatus()),
            preg_replace('/\<br\s*\/?\>/i', "\n", $item->getComment()),
            $tag
        );
    }
}