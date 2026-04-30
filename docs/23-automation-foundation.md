# Automation foundation (Phase 13.1-13.3)

## Goal

Keep automation structurally separate, scalable, and backend-replaceable, so future upgrades do not force rewrites across controllers and domain services.

## Structure

- Contracts:
  - `app/Contracts/Automation/AutomationProviderInterface.php`
- Providers:
  - `app/Automation/Providers/OpenSourceWebhookAutomationProvider.php`
- Engine:
  - `app/Services/Automation/AutomationEngine.php`
- Events:
  - `app/Automation/Events/InquiryFollowUpScheduled.php`
  - `app/Automation/Events/BookingConfirmed.php`
- Listeners:
  - `app/Automation/Listeners/TriggerInquiryFollowUpScheduledAutomation.php`
  - `app/Automation/Listeners/TriggerBookingConfirmedAutomation.php`
- Jobs:
  - `app/Automation/Jobs/DispatchInquiryFollowUpReminderJob.php`
- Scheduler / command:
  - `routes/console.php` (`automation:reminders:run`, every 5 minutes)

## Phase 13.1 - Reminder engine

- `automation:reminders:run` scans open `inquiry_follow_ups`:
  - `completed_at IS NULL`
  - `reminder_sent_at IS NULL`
  - `due_at <= now + lookahead`
- Matching records are queued via `DispatchInquiryFollowUpReminderJob`.
- Job dispatches reminder through `AutomationEngine`, then stamps `reminder_sent_at`.

## Phase 13.2 - Scheduled jobs

- Scheduler runs:
  - `automation:reminders:run`
  - `everyFiveMinutes()`
  - `withoutOverlapping()`
- Work execution stays queue-based for throughput and retry handling.

## Phase 13.3 - Event-based triggers

- Trigger points:
  - Follow-up created (`InquiryCrmService::scheduleFollowUp`) -> `InquiryFollowUpScheduled`
  - Booking confirmed (`BookingLifecycleManager::confirm`) -> `BookingConfirmed`
- Listeners are queued and call `AutomationEngine`, which calls the provider contract.

## Provider model (open-source now, swappable later)

- Default provider key: `open_source_webhook` (configurable).
- Current implementation:
  - POST reminder events to `/automation/reminder`
  - POST domain events to `/automation/event`
- Intended targets: open-source orchestrators (for example, n8n/self-hosted webhook workers).

## Configuration

See `config/automation.php`:

- `provider`
- `reminder_lookahead_minutes`
- `open_source_webhook.base_url`
- `open_source_webhook.token`
- `open_source_webhook.timeout_seconds`

## Swap strategy

To replace backend later:

1. Add a new provider class implementing `AutomationProviderInterface`.
2. Add provider config block (and optional env vars).
3. Extend provider binding in `AppServiceProvider`.

No controller, domain service, or database schema rewrites are required.
