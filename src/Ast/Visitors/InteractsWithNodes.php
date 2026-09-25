<?php

namespace Laravel\Chisel\Ast\Visitors;

use PhpParser\Node;

trait InteractsWithNodes
{
    /**
     * @param  array<Node\Stmt>  $ast  the top-level nodes.
     * @return array<Node\Stmt> the top-level statement nodes.
     */
    protected function getStatements(array $ast): array
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                return $node->stmts;
            }
        }

        return $ast;
    }

    /**
     * @param  array<Node\Stmt>  $ast  the top-level nodes.
     * @param  array<Node\Stmt>  $statements  the replacement statements.
     * @return array<Node\Stmt> the resulting top-level nodes.
     */
    protected function withStatements(array $ast, array $statements): array
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $node->stmts = $statements;

                return $ast;
            }
        }

        return $statements;
    }

    protected function simpleName(string $name): string
    {
        $parts = explode('\\', $name);

        return end($parts);
    }
}
