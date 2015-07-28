<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\InstallationBundle\DependencyInjection;

use Contao\CoreBundle\Config\ResourceFinder;
use Contao\CoreBundle\DependencyInjection\Compiler\AddResourcesPathsPass;
use Contao\CoreBundle\Session\Attribute\ArrayAttributeBag;
use Contao\Environment;
use Contao\InstallationBundle\Database\ConnectionFactory;
use Contao\InstallationBundle\Database\Installer;
use Contao\InstallationBundle\InstallTool;
use Contao\InstallationBundle\InstallToolUser;
use Contao\InstallationBundle\Translation\LanguageResolver;
use Contao\System;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;

/**
 * Creates a pre-configured service container.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ContainerFactory
{
    /**
     * Returns the container object.
     *
     * @param string $rootDir The root directoy
     *
     * @return ContainerBuilder The object instance
     */
    public static function create($rootDir)
    {
        $container = new ContainerBuilder();

        // Set up the kernel parameters
        $container->setParameter('kernel.root_dir', $rootDir);
        $container->setParameter('kernel.cache_dir', $rootDir . '/cache/prod');
        $container->setParameter('kernel.debug', false);

        // Load the parameters.yml file
        if (file_exists($rootDir . '/config/parameters.yml')) {
            $parameters = Yaml::parse(file_get_contents($rootDir . '/config/parameters.yml'));
        } else {
            $parameters = Yaml::parse(file_get_contents($rootDir . '/config/parameters.yml.dist'));
        }

        // Add the parameters to the container
        foreach ($parameters['parameters'] as $name => $value) {
            $container->setParameter($name, $value);
        }

        // Set up the request stack
        $request = Request::createFromGlobals();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $container->set('request_stack', $requestStack);

        // Create the session bag
        $bag = new ArrayAttributeBag('_contao_be_attributes');
        $bag->setName('contao_backend');

        // Start the session
        $session = new Session();
        $session->registerBag($bag);
        $session->start();
        $container->set('session', $session);

        // Set up the database connection
        $container->set('database_connection', ConnectionFactory::create($parameters));

        // Resolve the locale
        $translationsDir = __DIR__ . '/../Resources/translations';
        $resolver = new LanguageResolver($requestStack, $translationsDir);
        $locale = $resolver->getLocale();

        // Set up the translator
        $translator = new Translator($locale);
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('xlf', new XliffFileLoader());
        $translator->addResource('xlf', $translationsDir . '/messages.en.xlf', 'en');

        if ('en' !== $locale) {
            $translator->addResource('xlf', $translationsDir . '/messages.' . $locale . '.xlf', 'en');
        }

        $container->set('translator.default', $translator);

        // Set up Twig
        $twig = new \Twig_Environment(
            new \Twig_Loader_Filesystem(__DIR__ . '/../Resources/views')
        );

        $twig->addGlobal('path', $request->getBasePath());
        $twig->addGlobal('language', str_replace('_', '-', $locale));
        $twig->addGlobal('ua', Environment::get('agent')->class);

        $twig->addFunction(new \Twig_SimpleFunction('asset', function ($path) use ($request) {
            return ltrim($request->getBasePath() . '/' . $path, '/');
        }));

        $twig->addFilter(new \Twig_SimpleFilter('trans', function ($message, $params = []) use ($translator) {
            return $translator->trans($message, $params);
        }));

        $container->set('twig', $twig);

        // Add the kernel bundles
        $kernel = new \AppKernel('prod', false);
        $container->setParameter('kernel.bundles', $kernel->registerBundles());

        // Add the Contao resources paths
        $pass = new AddResourcesPathsPass();
        $pass->process($container);

        // Add the Contao resource finder
        $container->set(
            'contao.resource_finder',
            new ResourceFinder($container->getParameter('contao.resources_paths'))
        );

        // Add the Contao resource locator
        $container->set(
            'contao.resource_locator',
            new FileLocator($container->getParameter('contao.resources_paths'))
        );

        // Add the installer services
        $container->set(
            'contao.installer',
            new Installer(
                $container->get('database_connection'),
                $container->get('contao.resource_finder'),
                $container->get('translator.default')
            )
        );

        $container->set(
            'contao.install_tool',
            new InstallTool($container->get('database_connection'), $rootDir)
        );

        $container->set('contao.install_tool_user', new InstallToolUser($container->get('session')));

        // Make the container available in the Contao framework
        System::setContainer($container);

        return $container;
    }
}
