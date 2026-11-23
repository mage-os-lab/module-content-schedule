<?php
declare(strict_types=1);

namespace MageOS\ContentSchedule\Console\Command;

use MageOS\ContentSchedule\Cron\Apply;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Applies the due publish / unpublish dates now, as the cron job does.
 */
class Run extends Command
{
    /**
     * @param State $state
     * @param Apply $apply
     */
    public function __construct(
        private readonly State $state,
        private readonly Apply $apply
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('content-schedule:run')
            ->setDescription('Apply the due publish / unpublish dates of CMS pages and blocks now');
    }

    /**
     * Run the cron job in the crontab area, so saves behave as they do from cron; fails if any save failed.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = $this->state->emulateAreaCode(Area::AREA_CRONTAB, [$this->apply, 'execute']);
        if ($failed) {
            $output->writeln(sprintf('<error>%d page(s) or block(s) not saved: see the log.</error>', $failed));
            return Command::FAILURE;
        }
        $output->writeln('Due dates applied.');
        return Command::SUCCESS;
    }
}
