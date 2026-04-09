<?php

namespace App\Enums;

enum Department: int
{
    case SLITTING = 1;
    case TUBE_MAKING = 2;
    case POLISHING = 3;
    case PACKING = 4;

    public function label(): string
    {
        return match ($this) {
            self::SLITTING => 'Slitting',
            self::TUBE_MAKING => 'Tube Making',
            self::POLISHING => 'Polishing',
            self::PACKING => 'Packing',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->label(),
        ])->all();
    }

    public static function toArray(): array
    {
        return collect(self::cases())->map(fn($case) => [
            'id' => $case->value,
            'label' => $case->label(),
        ])->all();
    }

    public static function fromText(string $text): ?self
    {
        return match (strtolower($text)) {
            'slitting' => self::SLITTING,
            'tube making', 'tubing' => self::TUBE_MAKING,
            'polishing' => self::POLISHING,
            'packing' => self::PACKING,
            default => null,
        };
    }
}
