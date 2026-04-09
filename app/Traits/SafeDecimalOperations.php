<?php

namespace App\Traits;

/**
 * Trait SafeDecimalOperations
 * 
 * Provides precision-safe increment and decrement operations for decimal fields.
 * Uses BC Math functions to avoid floating-point precision errors that occur
 * with Laravel's native increment() and decrement() methods.
 * 
 * Usage:
 *   use App\Traits\SafeDecimalOperations;
 *   
 *   class MyModel extends Model
 *   {
 *       use SafeDecimalOperations;
 *   }
 *   
 *   // Then in controllers:
 *   $model->safeIncrement('field_name', $amount);
 *   $model->safeDecrement('field_name', $amount);
 */
trait SafeDecimalOperations
{
    /**
     * Safely increment a decimal field with proper precision
     * Uses BC Math (bcadd) to avoid floating-point errors
     * 
     * @param string $field The field name to increment
     * @param float|string|int $amount Amount to add
     * @param int $precision Decimal precision (default 4 to match most weight columns)
     * @return $this
     */
    public function safeIncrement(string $field, $amount, int $precision = 4): static
    {
        $current = (string) ($this->$field ?? 0);
        $amountStr = (string) $amount;
        $newValue = bcadd($current, $amountStr, $precision);
        
        $this->$field = $newValue;
        $this->save();
        
        return $this;
    }

    /**
     * Safely decrement a decimal field with proper precision
     * Uses BC Math (bcsub) to avoid floating-point errors
     * 
     * @param string $field The field name to decrement
     * @param float|string|int $amount Amount to subtract
     * @param int $precision Decimal precision (default 4 to match most weight columns)
     * @return $this
     */
    public function safeDecrement(string $field, $amount, int $precision = 4): static
    {
        $current = (string) ($this->$field ?? 0);
        $amountStr = (string) $amount;
        $newValue = bcsub($current, $amountStr, $precision);
        
        $this->$field = $newValue;
        $this->save();
        
        return $this;
    }

    /**
     * Safely increment multiple decimal fields at once
     * More efficient than multiple safeIncrement calls as it performs a single save
     * 
     * @param array $fields Array of field => amount pairs
     * @param int $precision Decimal precision
     * @return $this
     */
    public function safeIncrementMultiple(array $fields, int $precision = 4): static
    {
        foreach ($fields as $field => $amount) {
            $current = (string) ($this->$field ?? 0);
            $this->$field = bcadd($current, (string) $amount, $precision);
        }
        $this->save();
        
        return $this;
    }

    /**
     * Safely decrement multiple decimal fields at once
     * More efficient than multiple safeDecrement calls as it performs a single save
     * 
     * @param array $fields Array of field => amount pairs
     * @param int $precision Decimal precision
     * @return $this
     */
    public function safeDecrementMultiple(array $fields, int $precision = 4): static
    {
        foreach ($fields as $field => $amount) {
            $current = (string) ($this->$field ?? 0);
            $this->$field = bcsub($current, (string) $amount, $precision);
        }
        $this->save();
        
        return $this;
    }
}
