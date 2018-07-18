<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE_VAIMO.txt for license details.
 */
namespace Vaimo\ComposerRepositoryBundle\Repositories;

use Vaimo\ComposerRepositoryBundle\Composer\Config as ComposerConfig;

class BundlesRepository
{
    /**
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * @var \Vaimo\ComposerRepositoryBundle\Factories\PackageFactory
     */
    private $packageFactory;

    /**
     * @var \Vaimo\ComposerRepositoryBundle\Factories\CacheFactory
     */
    private $cacheFactory;

    /**
     * @var \Vaimo\ComposerRepositoryBundle\Bundle\DefinitionCollector
     */
    private $definitionCollector;

    /**
     * @param \Composer\Composer $composer
     * @param \Composer\IO\IOInterface $io
     */
    public function __construct(
        \Composer\Composer $composer,
        \Composer\IO\IOInterface $io
    ) {
        $this->composer = $composer;
        $this->io = $io;

        $this->packageFactory = new \Vaimo\ComposerRepositoryBundle\Factories\PackageFactory();
        $this->definitionCollector = new \Vaimo\ComposerRepositoryBundle\Bundle\DefinitionCollector();
        $this->cacheFactory = new \Vaimo\ComposerRepositoryBundle\Factories\CacheFactory();
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getPackages()
    {
        $repository = $this->composer->getRepositoryManager()->getLocalRepository();

        $packages = array_merge(
            array($this->composer->getPackage()),
            $repository->getPackages()
        );

        $bundles = $this->definitionCollector->collectBundleDefinitions($packages);

        $rootDir = dirname($this->composer->getConfig()->getConfigSource()->getName());

        $bundlePackages = array();

        $cache = $this->cacheFactory->create(
            $repository,
            $this->io,
            $this->composer->getConfig()->get(ComposerConfig::CACHE_DIR)
        );

        $cacheRoot = rtrim($cache->getRoot(), DIRECTORY_SEPARATOR);

        foreach ($bundles as $bundleName => $bundle) {
            if (isset($bundle['url'])) {
                $source = $bundle['url'];
            } else if (isset($bundle['source'])) {
                $source = $bundle['source'];
            } else {
                $source = '';
            }

            $uid = md5(
                serialize(array($source, isset($bundle['reference']) ? $bundle['reference'] : '0'))
            );

            $isLocalBundle = is_dir($rootDir . DIRECTORY_SEPARATOR . $source);

            if (!$isLocalBundle) {
                $target = isset($bundle['target'])
                    ? $rootDir . DIRECTORY_SEPARATOR . $bundle['target']
                    : ($cacheRoot . DIRECTORY_SEPARATOR . $uid);
            } else {
                $target = $source;
            }

            $package = $this->packageFactory->create(
                $bundleName,
                $source,
                $target,
                isset($bundle['reference']) ? $bundle['reference'] : null
            );

            $bundle['md5'] = $uid;
            $bundle['name'] = $bundleName;
            $bundle['local'] = $isLocalBundle;

            if (!isset($bundle['paths'])) {
                $bundle['paths'] = array('');
            }

            if (!is_array($bundle['paths'])) {
                throw new \Exception(
                    sprintf('Incorrect configuration for bundle "%s": value of "paths" should be an array', $bundleName)
                );
            }

            if (!$source) {
                $bundle['paths'] = array_filter($bundle['paths']);
            }

            if (!$source && !$bundle['paths']) {
                throw new \Exception(
                    sprintf('Incorrect configuration for bundle "%s": blank value for both source and paths', $bundleName)
                );
            }

            $package->setExtra($bundle);

            $bundlePackages[$bundleName] = $package;
        }

        return $bundlePackages;
    }
}
