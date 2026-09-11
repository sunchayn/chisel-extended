<?php

declare(strict_types=1);

namespace Laravel\Chisel\Ast\Visitors;

use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use PhpParser\NodeVisitorAbstract;

class RemoveImportVisitor extends NodeVisitorAbstract
{
    use InteractsWithNodes;

    /** @var array<string> */
    protected array $imports;

    /** @param  string|array<string>  $imports */
    public function __construct(string|array $imports)
    {
        $this->imports = is_array($imports) ? $imports : [$imports];
    }

    /**
     * @param  array<Stmt>  $nodes  the top-level nodes.
     * @return array<Stmt>|null the resulting top-level nodes.
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $statements = $this->getStatements($nodes);

        $filtered = [];

        foreach ($statements as $stmt) {
            if ($stmt instanceof Use_) {
                $remaining = array_filter($stmt->uses, function (UseItem $use): bool {
                    $fqcn = $use->name->toString();

                    foreach ($this->imports as $import) {
                        if ($fqcn === $import || $this->simpleName($fqcn) === $import) {
                            return false;
                        }
                    }

                    return true;
                });

                if ($remaining !== []) {
                    $stmt->uses = array_values($remaining);
                    $filtered[] = $stmt;
                }

                continue;
            }

            $filtered[] = $stmt;
        }

        return $this->withStatements($nodes, $filtered);
    }
}
