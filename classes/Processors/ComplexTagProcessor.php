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

require_once dirname(__FILE__) . '/../Contracts/TagProcessorInterface.php';
require_once dirname(__FILE__) . '/../Utils/CommonUtils.php';

/**
 * 复杂标签处理器
 *
 * 处理参数复杂的单标签,如 {include}
 *
 * 特点:
 * - 单标签,无闭合
 * - 参数可能包含数组、嵌套表达式、复杂字符串
 * - 使用逐字符大括号匹配,精确处理
 */
class ComplexTagProcessor implements TagProcessorInterface
{
    /**
     * @var array 支持的标签名称
     */
    protected $supportedTags = ['include'];

    /**
     * {@inheritdoc}
     */
    public function process($source, $smarty, $cleanSource)
    {
        $result = '';
        $offset = 0;
        $length = strlen($source);

        foreach ($this->supportedTags as $tagName) {
            $result = '';
            $offset = 0;
            $source = $this->processTag($source, $tagName, $smarty, $cleanSource);
        }

        return $source;
    }

    /**
     * 处理单个标签类型
     *
     * @param string $source 源代码
     * @param string $tagName 标签名称
     * @param Smarty $smarty Smarty 实例
     * @param string $cleanSource 清理后的源代码
     * @return string 处理后的代码
     */
    protected function processTag($source, $tagName, $smarty, $cleanSource)
    {
        $result = '';
        $offset = 0;
        $length = strlen($source);
        $searchPattern = '{' . $tagName;

        while ($offset < $length) {
            // 查找标签的开始位置
            $startPos = strpos($source, $searchPattern, $offset);

            // 如果没有更多标签了,添加剩余内容并退出
            if ($startPos === false) {
                $result .= substr($source, $offset);
                break;
            }

            // 添加当前位置到标签之间的内容
            $result .= substr($source, $offset, $startPos - $offset);

            // 找到标签的结束位置(平衡大括号)
            $endPos = CommonUtils::findMatchingBrace($source, $startPos);

            if ($endPos !== false) {
                $fullTag = substr($source, $startPos, $endPos - $startPos + 1);

                // 检查这个标签是否在注释中
                if (CommonUtils::isTagInComment($fullTag, $cleanSource)) {
                    $result .= $fullTag;
                    $offset = $endPos + 1;
                    continue;
                }

                // 提取文件名
                $label = $this->extractLabel($fullTag, $tagName);
                $absolutePath = CommonUtils::findAbsolutePath($label, $smarty);
                $safePath = CommonUtils::sanitizePathForComment($absolutePath);

                // 返回带有注释的标签
                $result .= "<!-- START " . strtoupper($tagName) . ": {$safePath} -->\n" .
                    $fullTag .
                    "\n<!-- END " . strtoupper($tagName) . ": {$safePath} -->";

                $offset = $endPos + 1;
            } else {
                // 如果找不到结束位置,添加当前字符并继续
                $result .= $source[$startPos];
                $offset = $startPos + 1;
            }
        }

        return $result;
    }

    /**
     * 从标签中提取标识符
     *
     * @param string $fullTag 完整标签
     * @param string $tagName 标签名称
     * @return string 标识符
     */
    protected function extractLabel($fullTag, $tagName)
    {
        // 提取 file 参数
        if (preg_match('/file=([\'"])([^\'"]+)\1/', $fullTag, $fileMatches)) {
            return $fileMatches[2];
        }

        // 如果无法提取,返回简化的标签内容
        return $tagName;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTags()
    {
        return $this->supportedTags;
    }

    /**
     * {@inheritdoc}
     */
    public function getTagType()
    {
        return 'complex';
    }
}
