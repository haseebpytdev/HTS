<?php

namespace App\Enums;

enum InquiryActivityType: string
{
    case Call = 'call';
    case Note = 'note';
    case StatusChange = 'status_change';
    case PipelineChange = 'pipeline_change';
    case FollowUpCompleted = 'follow_up_completed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
