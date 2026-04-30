# Support Desk (Phase 15.1-15.3)

## Goal

Create a dedicated support module with clear separation for ticketing, SLA policy, and escalation so it scales safely and remains easy to extend.

## Structure

- Config:
  - `config/support_desk.php`
- Enums:
  - `app/Enums/SupportTicketStatus.php`
  - `app/Enums/SupportTicketPriority.php`
- Models:
  - `app/Models/SupportTicket.php`
  - `app/Models/SupportTicketReply.php`
- Services:
  - `app/Services/SupportDesk/SupportDeskSlaService.php`
  - `app/Services/SupportDesk/SupportDeskService.php`
- Action:
  - `app/Actions/Admin/CreateSupportTicketAction.php`
- HTTP:
  - `app/Http/Controllers/Admin/SupportTicketController.php`
  - `app/Http/Requests/Admin/*SupportTicket*Request.php`
- Views:
  - `resources/views/admin/support-tickets/index.blade.php`
  - `resources/views/admin/support-tickets/show.blade.php`
- Routes:
  - `routes/admin.php` (`admin.support-tickets.*`)

## 15.1 Ticket system

- Added `support_tickets` and `support_ticket_replies` tables.
- Added ticket create/list/show/reply flows for admin users.
- Ticket includes references for agency/customer/assignee and channel.

## 15.2 SLA

- SLA policy defined in `config/support_desk.php` by priority.
- `SupportDeskSlaService` calculates first-response and resolution deadlines.
- Deadlines are stamped at ticket creation (`first_response_due_at`, `resolution_due_at`).

## 15.3 Escalation

- Escalation supported on ticket:
  - `escalated_to_user_id`
  - `escalated_at`
  - `escalation_reason`
- Escalation action reassigns ticket and moves status to in-progress.

## Communication Hub integration

- Support desk now routes notifications via Communication Hub jobs on:
  - public reply added
  - ticket escalated
- Routing policy:
  - base route follows ticket channel (`email`, `whatsapp`, `sms`, `portal`->email)
  - `high` and `urgent` priorities also trigger WhatsApp + SMS fan-out
- Internal in-app notifications:
  - assigned agent receives in-app notification on new public reply
  - escalated-to user receives in-app notification on escalation

## Notes

- Permissions added:
  - `module.support.view`
  - `action.support.manage`
- Dashboard quick link added to Support Desk.
- Communication Hub integration (email/whatsapp/sms) can be plugged in at service layer without changing ticket schema.
