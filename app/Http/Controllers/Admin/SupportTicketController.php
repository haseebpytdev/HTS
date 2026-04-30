<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateSupportTicketAction;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EscalateSupportTicketRequest;
use App\Http\Requests\Admin\FilterSupportTicketRequest;
use App\Http\Requests\Admin\ResolveSupportTicketRequest;
use App\Http\Requests\Admin\StoreSupportTicketReplyRequest;
use App\Http\Requests\Admin\StoreSupportTicketRequest;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportDesk\SupportDeskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly CreateSupportTicketAction $createTicket,
        private readonly SupportDeskService $supportDeskService
    ) {
    }

    public function index(FilterSupportTicketRequest $request): View
    {
        $this->authorize('access-admin-area');

        $filters = $request->validated();
        $tickets = SupportTicket::query()
            ->with(['assignee', 'creator', 'agency', 'customer'])
            ->when($filters['q'] ?? null, function ($query, string $q): void {
                $query->where(function ($nested) use ($q): void {
                    $nested->where('ticket_number', 'like', "%{$q}%")
                        ->orWhere('subject', 'like', "%{$q}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['assigned_to_user_id'] ?? null, fn ($query, int $userId) => $query->where('assigned_to_user_id', $userId))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.support-tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'statuses' => SupportTicketStatus::cases(),
            'priorities' => SupportTicketPriority::cases(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'agencies' => Agency::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']),
        ]);
    }

    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $ticket = $this->createTicket->execute($request->validated(), auth()->id());

        return redirect()->route('admin.support-tickets.show', $ticket)
            ->with('success', 'Support ticket created.');
    }

    public function show(SupportTicket $supportTicket): View
    {
        $this->authorize('access-admin-area');
        $supportTicket->load(['replies.user', 'assignee', 'creator', 'escalatedTo', 'agency', 'customer']);

        return view('admin.support-tickets.show', [
            'ticket' => $supportTicket,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reply(StoreSupportTicketReplyRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $this->supportDeskService->addReply(
            ticket: $supportTicket,
            message: $request->validated()['message'],
            userId: auth()->id(),
            isInternal: (bool) ($request->validated()['is_internal'] ?? false)
        );

        return redirect()->route('admin.support-tickets.show', $supportTicket)->with('success', 'Reply added.');
    }

    public function resolve(ResolveSupportTicketRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $this->supportDeskService->resolve($supportTicket, $request->validated()['note'] ?? null, auth()->id());

        return redirect()->route('admin.support-tickets.show', $supportTicket)->with('success', 'Ticket resolved.');
    }

    public function escalate(EscalateSupportTicketRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $this->supportDeskService->escalate(
            $supportTicket,
            (int) $request->validated()['to_user_id'],
            (string) $request->validated()['reason']
        );

        return redirect()->route('admin.support-tickets.show', $supportTicket)->with('success', 'Ticket escalated.');
    }
}
