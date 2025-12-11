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
 * Block 标签处理器
 *
 * 处理成对的 {block}...{/block} 标签
 *
 * 特点:
 * - 成对标签,需要配对开闭
 * - 支持嵌套
 * - 有自闭合形式: {block name='xxx'}{/block}
 * - 与模板继承协同工作
 * - 使用栈管理确保正确配对
 */
class BlockTagProcessor implements TagProcessorInterface
{
    /**
     * @var array Block 栈,用于配对开闭标签
     */
    protected $blockStack = [];

    /**
     * {@inheritdoc}
     */
    public function process($source, $smarty, $cleanSource)
    {
        $currentTemplate = CommonUtils::getCurrentTemplatePath($smarty);
        $safeTemplate = CommonUtils::sanitizePathForComment($currentTemplate);

        // 先处理自闭合的 block 标签 {block name='xxx'}{/block}
        $selfClosingPattern = '/\{block\s+name=([\'"])([^\'"]+)\1([^\}]*)\}\s*\{\/block\}/s';
        $source = preg_replace_callback($selfClosingPattern, function ($matches) use ($safeTemplate, $cleanSource) {
            // 检查是否在注释中
            if (CommonUtils::isTagInComment($matches[0], $cleanSource)) {
                return $matches[0];
            }

            $blockName = $matches[2];
            $params = $matches[3];

            // 对于自闭合标签,直接添加开始和结束注释
            return "<!-- START BLOCK: {$blockName} (in: {$safeTemplate}) -->\n" .
                "{block name='{$blockName}'{$params}}{/block}" .
                "\n<!-- END BLOCK: {$blockName} (in: {$safeTemplate}) -->";
        }, $source);

        // 重置 block 栈
        $this->blockStack = [];

        // 使用逐字符解析方法处理开始和结束标签
        $result = '';
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            // 查找最近的开始或结束标签
            $startPos = strpos($source, '{block', $offset);
            $endPos = strpos($source, '{/block}', $offset);

            // 如果没有更多标签了,添加剩余内容并退出
            if ($startPos === false && $endPos === false) {
                $result .= substr($source, $offset);
                break;
            }

            // 确定下一个要处理的标签位置
            if ($startPos === false) {
                $nextPos = $endPos;
                $isStart = false;
            } elseif ($endPos === false) {
                $nextPos = $startPos;
                $isStart = true;
            } else {
                $nextPos = min($startPos, $endPos);
                $isStart = ($nextPos == $startPos);
            }

            // 添加当前位置到下一个标签之间的内容
            $result .= substr($source, $offset, $nextPos - $offset);

            if ($isStart) {
                // 处理开始标签
                if (preg_match('/\{block\s+name=([\'"])([^\'"]+)\1([^\}]*)\}/s', $source, $matches, 0, $nextPos)) {
                    $fullMatch = $matches[0];
                    $blockName = $matches[2];
                    $params = $matches[3];

                    // 检查这个标签是否在注释中
                    if (CommonUtils::isTagInComment($fullMatch, $cleanSource)) {
                        $result .= $fullMatch;
                        $offset = $nextPos + strlen($fullMatch);
                        continue;
                    }

                    // 检查是否是自闭合标签(这种情况应该已经被上面处理过了,但为了安全再检查一遍)
                    if (substr($source, $nextPos + strlen($fullMatch), 9) === '{/block}') {
                        // 这种情况应该不会发生,因为我们已经处理过了自闭合标签
                        $result .= $fullMatch;
                        $offset = $nextPos + strlen($fullMatch);
                    } else {
                        // 普通开始标签 - 压栈
                        $this->blockStack[] = [
                            'name' => $blockName,
                            'template' => $safeTemplate,
                        ];

                        $result .= "<!-- START BLOCK: {$blockName} (in: {$safeTemplate}) -->\n" . $fullMatch;
                        $offset = $nextPos + strlen($fullMatch);
                    }
                } else {
                    // 不应该发生
                    $result .= substr($source, $nextPos, 1);
                    $offset = $nextPos + 1;
                }
            } else {
                // 处理结束标签
                // 检查这个标签是否在注释中
                if (CommonUtils::isTagInComment('{/block}', $cleanSource)) {
                    $result .= '{/block}';
                    $offset = $nextPos + 8; // '{/block}' 的长度是8
                    continue;
                }

                if (!empty($this->blockStack)) {
                    $blockInfo = array_pop($this->blockStack);
                    $blockName = $blockInfo['name'];
                    $template = $blockInfo['template'];

                    $result .= "{/block}\n<!-- END BLOCK: {$blockName} (in: {$template}) -->";
                } else {
                    // 没有匹配的开始标签
                    $result .= "{/block}\n<!-- END BLOCK -->";
                }
                $offset = $nextPos + 8; // '{/block}' 的长度是8
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTags()
    {
        return ['block'];
    }

    /**
     * {@inheritdoc}
     */
    public function getTagType()
    {
        return 'paired';
    }
}
