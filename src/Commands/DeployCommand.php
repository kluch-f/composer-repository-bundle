<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE_VAIMO.txt for license details.
 */
namespace Vaimo\ComposerRepositoryBundle\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeployCommand extends \Composer\Command\BaseCommand
{
    protected function configure()
    {
        $this->setName('bundle:deploy');

        $this->setDescription('Re-install package from a bundle');

        $this->addArgument(
            'packages',
            \Symfony\Component\Console\Input\InputArgument::IS_ARRAY,
            'Targeted bundle package name',
            []
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $composer = $this->getComposer();
        $io = $this->getIO();

        $packageNames = $input->getArgument('packages');

        $bundlesManager = new \Vaimo\ComposerRepositoryBundle\Managers\BundlesManager($composer, $io);

        $bundlesManager->bootstrap(false);

        $repository = $composer->getRepositoryManager()->getLocalRepository();

        $io->write('<info>Deploy bundle package(s)</info>');

        foreach ($packageNames as $package) {
            $package = $repository->findPackage($package, '*');

            $operation = new \Composer\DependencyResolver\Operation\InstallOperation($package);

            $composer->getInstallationManager()->install($repository, $operation);
        }
    }
}