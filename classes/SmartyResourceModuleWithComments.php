<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 *
 *
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

/**
 * Override module templates easily and add comments in non-dev mode.
 *
 * @since 1.7.0.0
 */
class SmartyResourceModuleWithComments extends Smarty_Resource_Custom
{
    /**
     * @var array<string>
     */
    public $paths;

    /**
     * @var bool
     */
    public $isAdmin;

    public function __construct(array $paths, $isAdmin = false)
    {
        $this->paths = $paths;
        $this->isAdmin = $isAdmin;
    }

    /**
     * Fetch a template.
     *
     * @param string $name template name
     * @param string $source template source
     * @param int $mtime template modification timestamp (epoch)
     */
    protected function fetch($name, &$source, &$mtime)
    {
        foreach ($this->paths as $path) {
            if (Tools::file_exists_cache($file = $path . $name)) {
                // Always add comments, regardless of dev mode
                $source = implode('', [
                    '<!-- START MODULE FETCH: ' . $file . ' -->',
                    file_get_contents($file),
                    '<!-- END MODULE FETCH: ' . $file . ' -->',
                ]);
                $mtime = filemtime($file);

                return;
            }
        }
    }
}
