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
 *
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

/**
 * 标签处理器接口
 *
 * 所有 Smarty 标签处理器必须实现此接口,确保统一的处理流程
 */
interface TagProcessorInterface
{
    /**
     * 处理指定标签,添加开发注释
     *
     * @param string $source 模板源代码
     * @param Smarty $smarty Smarty 实例
     * @param string $cleanSource 清理后的源代码(移除了注释)
     * @return string 处理后的模板代码
     */
    public function process($source, $smarty, $cleanSource);

    /**
     * 获取此处理器支持的标签名称列表
     *
     * @return array 标签名称数组,如 ['include'], ['hook', 'widget']
     */
    public function getSupportedTags();

    /**
     * 获取标签类型描述
     *
     * @return string 类型: 'simple', 'complex', 'paired', 'runtime-dynamic'
     */
    public function getTagType();
}
