<?php
/**
 * @author Andrew Keller <akeller@higherlogic.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Vanilla\Formatting\Rich2\Nodes;

use Vanilla\Utility\HtmlUtils;

class CodeBlock extends AbstractNode
{
    public bool $getChildren = false;

    /**
     * @inheritDoc
     */
    protected function getHtmlStart(): string
    {
        $lang = isset($this->data["lang"]) ? "language-{$this->data["lang"]}" : "";
        $attributes = HtmlUtils::attributes([
            "class" => "code codeBlock $lang",
            "spellcheck" => "false",
            "tabindex" => "0",
        ]);
        return "<pre $attributes>";
    }

    /**
     * @inheritDoc
     */
    protected function getHtmlEnd(): string
    {
        return "</pre>";
    }

    /**
     * @inheritDoc
     */
    public static function matches(array $node): bool
    {
        return isset($node["type"]) && $node["type"] === "code_block";
    }
}
