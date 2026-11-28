<?php

namespace SignageFlow\Hrms\Services;

class FormulaEvaluator
{
    /**
     * Safely evaluate an arithmetic formula with named variables (no eval()).
     * Supports +, -, *, /, parentheses, and numeric literals.
     */
    public function evaluate(string $expression, array $variables = []): float
    {
        $expr = strtoupper(trim($expression));
        if ($expr === '') {
            return 0.0;
        }

        // Sort longest variable names first to avoid partial replacements
        uksort($variables, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        foreach ($variables as $key => $val) {
            $expr = preg_replace('/\b'.preg_quote(strtoupper((string) $key), '/').'\b/', (string) (float) $val, $expr);
        }

        // Reject anything other than digits, decimal points, operators, spaces, parentheses
        if (! preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return 0.0;
        }

        $tokens = $this->tokenize($expr);
        $pos = 0;

        return round($this->parseExpr($tokens, $pos), 2);
    }

    protected function tokenize(string $expr): array
    {
        preg_match_all('/\d+(?:\.\d+)?|[+\-*\/()]/', $expr, $matches);

        return $matches[0] ?? [];
    }

    protected function parseExpr(array $tokens, int &$pos): float
    {
        $val = $this->parseTerm($tokens, $pos);
        while (isset($tokens[$pos]) && in_array($tokens[$pos], ['+', '-'], true)) {
            $op = $tokens[$pos++];
            $rhs = $this->parseTerm($tokens, $pos);
            $val = $op === '+' ? $val + $rhs : $val - $rhs;
        }

        return $val;
    }

    protected function parseTerm(array $tokens, int &$pos): float
    {
        $val = $this->parseFactor($tokens, $pos);
        while (isset($tokens[$pos]) && in_array($tokens[$pos], ['*', '/'], true)) {
            $op = $tokens[$pos++];
            $rhs = $this->parseFactor($tokens, $pos);
            $val = $op === '*' ? $val * $rhs : ($rhs != 0.0 ? $val / $rhs : 0.0);
        }

        return $val;
    }

    protected function parseFactor(array $tokens, int &$pos): float
    {
        if (! isset($tokens[$pos])) {
            return 0.0;
        }

        if ($tokens[$pos] === '-') {
            $pos++;

            return -$this->parseFactor($tokens, $pos);
        }

        if ($tokens[$pos] === '(') {
            $pos++;
            $val = $this->parseExpr($tokens, $pos);
            if (isset($tokens[$pos]) && $tokens[$pos] === ')') {
                $pos++;
            }

            return $val;
        }

        return (float) $tokens[$pos++];
    }
}
