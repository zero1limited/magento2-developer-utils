<?php

namespace MDOQ\SitePerformanceMonitoring\Console\Command;


use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Scommerce\RepeatOrder\Cron\CreditCardExpiration;
use Magento\Framework\ObjectManagerInterface;
use MDOQ\SitePerformanceMonitoring\Api\SiteRepositoryInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\Table;
use Magento\Framework\Api\SearchCriteriaBuilder;

class TestModelCommand extends Command
{
    private const INPUT_OPTION_NAME = 'name';

    private const INPUT_ARGUMENT_DRY_RUN = 'dry-run';

    /**
     * ProcessSubscriptionsCommand constructor.
     * @param \Magento\Framework\App\State $state
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        protected SiteRepositoryInterface $siteRepository,
        protected SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('vendor:module:model-test')
            ->setDescription('Test the model and repository');
        // --name=somevalue
        $this->addOption(
            self::INPUT_OPTION_NAME,
            null,
            InputOption::VALUE_REQUIRED,
            'Name'
        );
        // --dry-run
        $this->addArgument(
            self::INPUT_ARGUMENT_DRY_RUN,
            InputArgument::OPTIONAL,
            'Dry Run'
        );
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getOption(self::INPUT_OPTION_NAME);
        $dryRun = $input->getArgument(self::INPUT_ARGUMENT_DRY_RUN);

        if ($dryRun) {
            $output->writeln('<info>Dry run mode enabled, can\'t do anything.</info>');
            return Cli::RETURN_SUCCESS;
        }

        if(!$name) {
            $output->writeln('<error>Name is required. Use --name=somevalue</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('Testing model and repository');

        $site = $this->siteRepository->getNew();

        $site->setName($name);
        try{
            $this->siteRepository->save($site);
        }catch(\Magento\Framework\Validator\Exception $e){
            $output->writeln('<error>Validation failed: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
        
        $output->writeln('<info>Site created with ID ' . $site->getId() . '</info>');

        $loadedSite = $this->siteRepository->getById($site->getId());
        $output->writeln('<info>Site loaded (by id) with name: ' . $loadedSite->getName() . '</info>');
        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Created At', 'Updated At'])
            ->setRows([
                [$loadedSite->getId(), $loadedSite->getName(), $loadedSite->getCreatedAt(), $loadedSite->getUpdatedAt()]
            ]);
        $table->render();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('id', $site->getId(), 'eq')
            ->create();
        $sites = $this->siteRepository->getList($searchCriteria);
        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Created At', 'Updated At']);
        $rows = [];
        foreach ($sites->getItems() as $siteItem) {
            $rows[] = [$siteItem->getId(), $siteItem->getName(), $siteItem->getCreatedAt(), $siteItem->getUpdatedAt()];
        }
        $table->setRows($rows);
        $output->writeln('<info>Sites loaded (by search criteria):</info>');
        $table->render();

        return Cli::RETURN_SUCCESS;
    }
}
