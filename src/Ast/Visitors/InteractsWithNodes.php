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
        if (count($ast) === 1 && $ast[0] instanceof Node\Stmt\Namespace_) {
            return $ast[0]->stmts;
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
        if (count($ast) === 1 && $ast[0] instanceof Node\Stmt\Namespace_) {
            $ast[0]->stmts = $statements;

            return $ast;
        }

        return $statements;
    }

    protected function simpleName(string $name): string
    {
        $parts = explode('\\', $name);

        return end($parts);
    }
}
