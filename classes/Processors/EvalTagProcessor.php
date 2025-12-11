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
 * Eval 标签处理器
 *
 * 处理 {eval} 标签 - 特殊的运行时动态标签
 *
 * 特点:
 * - 单标签,无闭合
 * - 内容在运行时动态执行,编译时不可见
 * - 可能包含嵌套的 block/include 等标签(但我们无法预知)
 * - 添加警告注释提示开发者
 */
class EvalTagProcessor implements TagProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function process($source, $smarty, $cleanSource)
    {
        $result = '';
        $offset = 0;
        $length = strlen($source);
        $currentTemplate = CommonUtils::getCurrentTemplatePath($smarty);
        $safeTemplate = CommonUtils::sanitizePathForComment($currentTemplate);

        while ($offset < $length) {
            // 查找 eval 标签的开始位置
            $startPos = strpos($source, '{eval', $offset);

            // 如果没有更多 eval 标签了,添加剩余内容并退出
            if ($startPos === false) {
                $result .= substr($source, $offset);
                break;
            }

            // 添加当前位置到 eval 标签之间的内容
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

                // 尝试提取有意义的标识(如 var=... 或代码片段)
                $label = $this->extractLabel($fullTag);

                // 返回带有注释的 eval 标签
                $result .= "<!-- START EVAL: {$label} (in: {$safeTemplate}) -->\n" .
                    $fullTag .
                    "\n<!-- END EVAL: {$label} -->";

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
     * 从 eval 标签中提取标识符
     *
     * @param string $fullTag 完整标签
     * @return string 标识符
     */
    protected function extractLabel($fullTag)
    {
        // 尝试提取 var 参数
        if (preg_match('/var=([\'"])([^\'"]+)\1/', $fullTag, $m)) {
            return $m[2];
        } elseif (preg_match('/var=([^\s\}]+)/', $fullTag, $m2)) {
            return $m2[1];
        }

        // 如果没有 var 参数,使用 eval 内容的简短摘要(去除 {eval 和 })
        $inner = trim(substr($fullTag, 5, -1)); // 去除 {eval 和 }
        $inner = preg_replace('/\s+/', ' ', $inner);
        $label = substr($inner, 0, 80);

        if ($label === '') {
            $label = 'eval';
        }

        return $label;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTags()
    {
        return ['eval'];
    }

    /**
     * {@inheritdoc}
     */
    public function getTagType()
    {
        return 'runtime-dynamic';
    }
}
