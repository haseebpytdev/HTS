<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConvertInquiryToBookingIntentRequest;
use App\Http\Requests\Admin\FilterInquiryRequest;
use App\Http\Requests\Admin\UpdateInquiryStatusRequest;
use App\Models\BookingIntent;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\Quotation;
use App\Models\Destination;
use App\Models\TravelPackage;
use App\Models\User;
use App\Services\Crm\InquiryCrmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function __construct(
        private readonly InquiryCrmService $crm
    ) {}

    public function index(FilterInquiryRequest $request): View
    {
        $this->authorize('access-admin-area');

        $filters = $request->validated();
        $inquiries = Inquiry::query()
            ->with(['agency', 'package', 'group', 'quotations', 'assignedTo:id,name'])
            ->withCount(['quotations', 'followUps'])
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['pipeline_stage'] ?? null, fn ($query, string $stage) => $query->where('pipeline_stage', $stage))
            ->when($filters['source'] ?? null, fn ($query, string $source) => $query->where('source', $source))
            ->when($filters['agency_id'] ?? null, fn ($query, int $agencyId) => $query->where('agency_id', $agencyId))
            ->when($filters['package_id'] ?? null, fn ($query, int $packageId) => $query->where('package_id', $packageId))
            ->when($filters['destination_id'] ?? null, fn ($query, int $destinationId) => $query->where('destination_id', $destinationId))
            ->when($filters['assigned_to'] ?? null, fn ($query, int $assigneeId) => $query->where('assigned_to', $assigneeId))
            ->when(array_key_exists('has_quotation', $filters), function ($query) use ($filters): void {
                if (($filters['has_quotation'] ?? null) === '1') {
                    $query->has('quotations');
                }
                if (($filters['has_quotation'] ?? null) === '0') {
                    $query->doesntHave('quotations');
                }
            })
            ->when(array_key_exists('has_open_follow_up', $filters), function ($query) use ($filters): void {
                if (($filters['has_open_follow_up'] ?? null) === '1') {
                    $query->whereHas('followUps', fn ($fq) => $fq->whereNull('completed_at'));
                }
                if (($filters['has_open_follow_up'] ?? null) === '0') {
                    $query->whereDoesntHave('followUps', fn ($fq) => $fq->whereNull('completed_at'));
                }
            })
            ->when($filters['created_from'] ?? null, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['created_to'] ?? null, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.inquiries.index', [
            'inquiries' => $inquiries,
            'filters' => $filters,
            'statuses' => Inquiry::statuses(),
            'pipelineStages' => \App\Enums\LeadPipelineStage::cases(),
            'agencies' => Agency::query()->orderBy('name')->get(['id', 'name']),
            'packages' => TravelPackage::query()->orderBy('title')->get(['id', 'title']),
            'destinations' => Destination::query()->orderBy('name')->get(['id', 'name']),
            'assignableUsers' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Inquiry $inquiry): View
    {
        $this->authorize('access-admin-area');
        $this->authorize('view', $inquiry);
        $inquiry->load([
            'agency',
            'destination',
            'package',
            'group',
            'quotations',
            'activities.user',
            'followUps.assignee',
            'followUps.creator',
        ]);

        $eligibleQuotations = Quotation::query()
            ->where('inquiry_id', $inquiry->id)
            ->latest('id')
            ->get(['id', 'quote_number', 'status', 'agency_id']);

        $assignableUsers = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.inquiries.show', [
            'inquiry' => $inquiry,
            'statuses' => Inquiry::statuses(),
            'pipelineStages' => \App\Enums\LeadPipelineStage::cases(),
            'eligibleQuotations' => $eligibleQuotations,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function updateStatus(UpdateInquiryStatusRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $inquiry);

        $oldStatus = $inquiry->status;
        $inquiry->update($request->validated());
        $inquiry->refresh();

        if ($oldStatus !== $inquiry->status) {
            $this->crm->recordStatusChange($inquiry, $oldStatus, $inquiry->status, auth()->id());
        }

        return redirect()->route('admin.inquiries.show', $inquiry)
            ->with('success', 'Inquiry status and notes updated.');
    }

    public function convertToBookingIntent(ConvertInquiryToBookingIntentRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $this->authorize('update', $inquiry);

        $quotation = Quotation::query()
            ->where('id', $request->validated()['quotation_id'])
            ->where('inquiry_id', $inquiry->id)
            ->firstOrFail();

        $exists = BookingIntent::query()
            ->where('quotation_id', $quotation->id)
            ->where('agency_id', $quotation->agency_id)
            ->exists();

        if (! $exists) {
            BookingIntent::query()->create([
                'quotation_id' => $quotation->id,
                'agency_id' => $quotation->agency_id,
                'user_id' => auth()->id(),
                'note' => $request->validated()['note'] ?? 'Converted from inquiry by admin.',
                'status' => 'interested',
                'requested_at' => now(),
            ]);
        }

        $oldStatus = $inquiry->status;
        $inquiry->update([
            'status' => Inquiry::STATUS_CONFIRMED,
            'admin_notes' => trim(($inquiry->admin_notes ?? '').PHP_EOL.'Converted to booking intent from quote '.$quotation->quote_number),
        ]);

        if ($oldStatus !== Inquiry::STATUS_CONFIRMED) {
            $this->crm->recordStatusChange($inquiry->fresh(), $oldStatus, Inquiry::STATUS_CONFIRMED, auth()->id());
        }

        $this->crm->markConverted(
            $inquiry->fresh(),
            'Converted to booking intent from quote '.$quotation->quote_number.'.',
            auth()->id()
        );

        return redirect()->route('admin.inquiries.show', $inquiry)
            ->with('success', 'Inquiry converted to booking intent successfully.');
    }
}
