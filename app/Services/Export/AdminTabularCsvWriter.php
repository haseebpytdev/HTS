<?php

namespace App\Services\Export;

use App\Models\Booking;
use App\Models\Inquiry;

/**
 * Writes admin CSV exports to an open stream handle (keeps controllers thin).
 */
class AdminTabularCsvWriter
{
    /**
     * @param  resource  $handle
     */
    public function writeInquiries($handle): void
    {
        fputcsv($handle, [
            'id',
            'source',
            'status',
            'name',
            'email',
            'phone',
            'travel_date',
            'adults',
            'children',
            'budget',
            'currency',
            'agency',
            'package',
            'group',
            'created_at',
        ]);

        Inquiry::query()
            ->with(['agency', 'package', 'group'])
            ->orderBy('id')
            ->chunk(300, function ($inquiries) use ($handle): void {
                foreach ($inquiries as $inquiry) {
                    fputcsv($handle, [
                        $inquiry->id,
                        $inquiry->source,
                        $inquiry->status,
                        $inquiry->name,
                        $inquiry->email,
                        $inquiry->phone,
                        optional($inquiry->travel_date)->format('Y-m-d'),
                        $inquiry->adults,
                        $inquiry->children,
                        $inquiry->budget,
                        $inquiry->currency,
                        $inquiry->agency?->name,
                        $inquiry->package?->title,
                        $inquiry->group?->name,
                        optional($inquiry->created_at)->format('Y-m-d H:i:s'),
                    ]);
                }
            });
    }

    /**
     * @param  resource  $handle
     */
    public function writeBookings($handle): void
    {
        fputcsv($handle, [
            'id',
            'booking_number',
            'status',
            'payment_status',
            'quote_number',
            'agency',
            'booked_at',
            'total_amount',
            'currency',
            'created_at',
        ]);

        Booking::query()
            ->with(['quotation', 'agency'])
            ->orderBy('id')
            ->chunk(300, function ($bookings) use ($handle): void {
                foreach ($bookings as $booking) {
                    fputcsv($handle, [
                        $booking->id,
                        $booking->booking_number,
                        $booking->status,
                        $booking->payment_status,
                        $booking->quotation?->quote_number,
                        $booking->agency?->name,
                        optional($booking->booked_at)->format('Y-m-d H:i:s'),
                        $booking->total_amount,
                        $booking->currency,
                        optional($booking->created_at)->format('Y-m-d H:i:s'),
                    ]);
                }
            });
    }
}
