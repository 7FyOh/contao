<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\FaqBundle\Tests\ContaoManager;

use Contao\FaqBundle\ContaoManager\Plugin;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;

/**
 * Tests the Plugin class.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests the object instantiation.
     */
    public function testInstantiation()
    {
        $plugin = new Plugin();

        $this->assertInstanceOf('Contao\FaqBundle\ContaoManager\Plugin', $plugin);
    }

    /**
     * Tests the getBundles() method.
     */
    public function testGetBundles()
    {
        $parser = $this->getMock('Contao\ManagerPlugin\Bundle\Parser\ParserInterface');

        /** @var BundleConfig $config */
        $config = (new Plugin())->getBundles($parser)[0];

        $this->assertInstanceOf('Contao\ManagerPlugin\Bundle\Config\BundleConfig', $config);
        $this->assertSame('Contao\FaqBundle\ContaoFaqBundle', $config->getName());
        $this->assertSame(['Contao\CoreBundle\ContaoCoreBundle'], $config->getLoadAfter());
        $this->assertSame(['faq'], $config->getReplace());
    }
}
