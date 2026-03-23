<?php

declare(strict_types=1);

namespace TestQualityAnalyzer\Visitor;

use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use TestQualityAnalyzer\VisitorInterface;

abstract class AbstractTestVisitor extends NodeVisitorAbstract implements VisitorInterface
{
    protected ?string $currentFile = null;

    public function setCurrentFile(string $file): void
    {
        $this->currentFile = $file;
    }

    protected function isTestMethod(ClassMethod $node): bool
    {
        if (str_starts_with($node->name->name, 'test')) {
            return true;
        }

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $attr->name->toString();
                if ($attrName === 'Test' || str_ends_with($attrName, '\Test')) {
                    return true;
                }
            }
        }

        return false;
    }
}
