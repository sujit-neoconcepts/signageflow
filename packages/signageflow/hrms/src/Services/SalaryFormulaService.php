<?php

namespace SignageFlow\Hrms\Services;

class SalaryFormulaService
{
    /**
     * Safely evaluate an arithmetic salary formula without eval().
     * Supports +, -, *, /, parentheses, and variables like BASIC, GROSS, CTC.
     */
    public function evaluate(string $expression, array $variables = []): float
    {
        $expr = strtoupper(trim($expression));
        if ($expr === '' || is_numeric($expr)) {
            return round((float) $expr, 2);
        }

        foreach ($variables as $key => $value) {
            $expr = preg_replace('/\b'.preg_quote(strtoupper((string) $key), '/').'\b/', (string) ((float) $value), $expr);
        }

        // Only allow digits, decimal points, whitespace, operators, and parentheses
        if (! preg_match('/^[0-9\.\+\-\*\/\(\)\s]+$/', $expr)) {
            return 0.0;
        }

        $tokens = $this->tokenize($expr);
        $pos = 0;

        try {
            $result = $this->parseExpression($tokens, $pos);

            return round($result, 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    protected function tokenize(string $expr): array
    {
        preg_match_all('/\d+(?:\.\d+)?|[\+\-\*\/\(\)]/', $expr, $matches);

        return $matches[0] ?? [];
    }

    protected function parseExpression(array $tokens, int &$pos): float
    {
        $val = $this->parseTerm($tokens, $pos);
        while ($pos < count($tokens) && in_array($tokens[$pos], ['+', '-'], true)) {
            $op = $tokens[$pos++];
            $rhs = $this->parseTerm($tokens, $pos);
            $val = $op === '+' ? $val + $rhs : $val - $rhs;
        }

        return $val;
    }

    protected function parseTerm(array $tokens, int &$pos): float
    {
        $val = $this->parseFactor($tokens, $pos);
        while ($pos < count($tokens) && in_array($tokens[$pos], ['*', '/'], true)) {
            $op = $tokens[$pos++];
            $rhs = $this->parseFactor($tokens, $pos);
            $val = $op === '*' ? $val * $rhs : ($rhs != 0.0 ? $val / $rhs : 0.0);
        }

        return $val;
    }

    protected function parseFactor(array $tokens, int &$pos): float
    {
        if ($pos >= count($tokens)) {
            return 0.0;
        }

        $tok = $tokens[$pos];
        if ($tok === '-') {
            $pos++;

            return -$this->parseFactor($tokens, $pos);
        }
        if ($tok === '(') {
            $pos++;
            $val = $this->parseExpression($tokens, $pos);
            if ($pos < count($tokens) && $tokens[$pos] === ')') {
                $pos++;
            }

            return $val;
        }

        $pos++;

        return (float) $tok;
    }
}
