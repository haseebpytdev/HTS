<?php

namespace App\Enums;

enum LeadPipelineStage: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Quoted = 'quoted';
    case Converted = 'converted';
    case Lost = 'lost';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Quoted => 'Quoted',
            self::Converted => 'Converted',
            self::Lost => 'Lost',
        };
    }
}
