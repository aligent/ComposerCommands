<?php

namespace Aligent\Composer\Magento\Command;
use MagentoHackathon\Composer\Magento\Deploy\Manager\Entry;
use MagentoHackathon\Composer\Magento\DeployManager;
use MagentoHackathon\Composer\Magento\Event\EventManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Downloader\VcsDownloader;
use MagentoHackathon\Composer\Magento\Installer;

class ManualDeployCommand extends \Composer\Command\Command
{
    protected function configure()
    {
        $this
            ->setName('magento-manual-deploy')
            ->setDescription('Deploy all Magento modules loaded via composer.json')
            ->setDefinition(array(
                new InputOption('strategy', '-s', InputOption::VALUE_REQUIRED, 'Set Deploy Strategy (copy/ symlink)'),
            ))
            ->setHelp(<<<EOT
This command deploys all magento Modules

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // init repos
        $composer = $this->getComposer();
        $installedRepo = $composer->getRepositoryManager()->getLocalRepository();

        $dm = $composer->getDownloadManager();
        $im = $composer->getInstallationManager();

        /**
         * @var $moduleInstaller \MagentoHackathon\Composer\Magento\Installer
         */
        $moduleInstaller = $im->getInstaller("magento-module");

        $eventManager   = new EventManager;
        $deployManager  = new DeployManager($eventManager);

        $io = $this->getIo();
        if ($io->isDebug()) {
            $eventManager->listen('pre-package-deploy', function(PackageDeployEvent $event) use ($io) {
                $io->write('Start magento deploy for ' . $event->getDeployEntry()->getPackageName());
            });
        }

        $extra          = $composer->getPackage()->getExtra();
        $sortPriority   = isset($extra['magento-deploy-sort-priority']) ? $extra['magento-deploy-sort-priority'] : array();
        $deployManager->setSortPriority( $sortPriority );



        $moduleInstaller->setDeployManager( $deployManager );


        foreach ($installedRepo->getPackages() as $package) {

            if ($input->getOption('verbose')) {
                $output->writeln( $package->getName() );
                $output->writeln( $package->getType() );
            }

            if( $package->getType() != "magento-module" ){
                continue;
            }
            if ($input->getOption('verbose')) {
                $output->writeln("package {$package->getName()} recognized");
            }

            // Set input strategy (overwrite composer.json)
            if ($vStrategy = $input->getOption('strategy')) {
                $moduleInstaller->setDeployStrategy($vStrategy);
            }

            $strategy = $moduleInstaller->getDeployStrategy($package);

            if ($input->getOption('verbose')) {
                $output->writeln("used " . get_class($strategy) . " as deploy strategy");
            }
            $strategy->setMappings($moduleInstaller->getParser($package)->getMappings());

            $deployManagerEntry = new Entry();
            $deployManagerEntry->setPackageName($package->getName());
            $deployManagerEntry->setDeployStrategy($strategy);
            $deployManager->addPackage($deployManagerEntry);

        }

        $deployManager->doDeploy();

        return;
    }
}
