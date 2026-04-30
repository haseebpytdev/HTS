# CRM and sales pipeline (Phase 7)

## Overview

Leads are **`inquiries`** extended with a **sales pipeline stage**, optional **estimated deal value**, **last contact** timestamp, an **activity timeline** (`inquiry_activities`), and **follow-ups** (`inquiry_follow_ups`). Business rules live in **`App\Services\Crm\InquiryCrmService`**; HTTP is thin (**`InquiryCrmController`** + Form Requests).

## Pipeline stages

Backed enum **`App\Enums\LeadPipelineStage`**:

| Value | Meaning |
|-------|---------|
| `new` | Fresh lead |
| `contacted` | First touch |
| `quoted` | Proposal sent (aligns with revision/quote work as needed) |
| `converted` | Won (e.g. booking intent / handoff) |
| `lost` | Closed without conversion |

Operational **`inquiry.status`** (new, contacted, quoted, etc.) remains separate for workflow detail; changing status from the admin form appends a **`status_change`** activity. Converting to booking intent sets **`confirmed`** status, logs status change when applicable, and moves the pipeline to **`converted`** via **`markConverted()`**.

## Activities

**`inquiry_activities`** rows include:

- **`call`** — optional title + summary; updates **`inquiries.last_contacted_at`**
- **`note`** — free-text note
- **`status_change`** — metadata `from` / `to` status
- **`pipeline_change`** — metadata `from` / `to` pipeline stage
- **`follow_up_completed`** — optional completion note; metadata includes `follow_up_id`

## Follow-ups

**`inquiry_follow_ups`**: title, description, **`due_at`**, optional **`assigned_to`**, **`created_by`**, **`completed_at`**, **`reminder_sent_at`** (reserved for future notification/reminder jobs).

Completing a follow-up sets **`completed_at`** and appends a **`follow_up_completed`** activity.

## Admin routes

| Method | Route | Name |
|--------|-------|------|
| POST | `admin/inquiries/{inquiry}/crm/calls` | `admin.inquiries.crm.calls` |
| POST | `admin/inquiries/{inquiry}/crm/notes` | `admin.inquiries.crm.notes` |
| PATCH | `admin/inquiries/{inquiry}/crm/pipeline` | `admin.inquiries.crm.pipeline` |
| POST | `admin/inquiries/{inquiry}/crm/follow-ups` | `admin.inquiries.crm.follow-ups.store` |
| PATCH | `admin/inquiries/{inquiry}/crm/follow-ups/{followUp}/complete` | `admin.inquiries.crm.follow-ups.complete` |

**`InquiryFollowUp`** uses **`resolveRouteBinding`** scoped to the parent inquiry.

## API

**`InquiryResource`** exposes **`pipeline_stage`**, **`estimated_value`**, and **`last_contacted_at`** for consumers (e.g. Hayat Travel Solutions sync).

## Follow-up reminders (optional next step)

No queue/notification is wired by default. To operationalize reminders, add a scheduled command that selects open follow-ups where **`due_at`** is near and **`reminder_sent_at`** is null, sends mail/push, then sets **`reminder_sent_at`**.

## Tests

**`tests/Feature/Admin/InquiryCrmTest.php`** covers notes, pipeline updates, calls, follow-up completion, status-change logging, and pipeline filtering on the inquiry index.
