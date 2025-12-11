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

require_once dirname(__FILE__) . '/Contracts/TagProcessorInterface.php';
require_once dirname(__FILE__) . '/Processors/SimpleTagProcessor.php';
require_once dirname(__FILE__) . '/Processors/ComplexTagProcessor.php';
require_once dirname(__FILE__) . '/Processors/BlockTagProcessor.php';
require_once dirname(__FILE__) . '/Processors/EvalTagProcessor.php';

/**
 * 标签处理器工厂
 *
 * 管理和协调所有标签处理器的创建和执行
 */
class TagProcessorFactory
{
    /**
     * @var array 处理器实例缓存
     */
    protected static $processors = null;

    /**
     * 获取所有已注册的处理器
     *
     * @return array TagProcessorInterface[]
     */
    public static function getProcessors()
    {
        if (self::$processors === null) {
            self::$processors = [
                new SimpleTagProcessor(),
                new ComplexTagProcessor(),
                new BlockTagProcessor(),
                new EvalTagProcessor(),
            ];
        }

        return self::$processors;
    }

    /**
     * 注册自定义处理器
     *
     * 允许扩展功能,添加新的标签处理器
     *
     * @param TagProcessorInterface $processor 处理器实例
     */
    public static function registerProcessor(TagProcessorInterface $processor)
    {
        if (self::$processors === null) {
            self::getProcessors();
        }

        self::$processors[] = $processor;
    }

    /**
     * 按标签名称获取处理器
     *
     * @param string $tagName 标签名称
     * @return TagProcessorInterface|null 处理器或 null
     */
    public static function getProcessorForTag($tagName)
    {
        $processors = self::getProcessors();

        foreach ($processors as $processor) {
            if (in_array($tagName, $processor->getSupportedTags())) {
                return $processor;
            }
        }

        return null;
    }

    /**
     * 获取所有支持的标签名称
     *
     * @return array 标签名称数组
     */
    public static function getSupportedTags()
    {
        $tags = [];
        $processors = self::getProcessors();

        foreach ($processors as $processor) {
            $tags = array_merge($tags, $processor->getSupportedTags());
        }

        return array_unique($tags);
    }

    /**
     * 清除处理器缓存(用于测试)
     */
    public static function clearCache()
    {
        self::$processors = null;
    }
}
