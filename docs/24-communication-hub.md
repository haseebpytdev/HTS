# Communication Hub (Phase 14.1-14.4)

## Goal

Provide a dedicated, scalable communication structure where Email, WhatsApp, SMS, and in-app notifications are managed through separate channel contracts and swappable providers.

## Structure

- Config:
  - `config/communication.php`
- Message DTO:
  - `app/Data/Communication/CommunicationMessageData.php`
- Channel contracts:
  - `app/Contracts/Communication/EmailChannelInterface.php`
  - `app/Contracts/Communication/WhatsappChannelInterface.php`
  - `app/Contracts/Communication/SmsChannelInterface.php`
  - `app/Contracts/Communication/InAppNotificationChannelInterface.php`
- Channel providers:
  - `app/Communication/Channels/LaravelMailEmailChannel.php`
  - `app/Communication/Channels/OpenSourceWebhookWhatsappChannel.php`
  - `app/Communication/Channels/OpenSourceWebhookSmsChannel.php`
  - `app/Communication/Channels/DatabaseInAppNotificationChannel.php`
- Hub service:
  - `app/Services/Communication/CommunicationHub.php`
- Queue jobs:
  - `app/Jobs/Communication/SendEmailMessageJob.php`
  - `app/Jobs/Communication/SendWhatsappMessageJob.php`
  - `app/Jobs/Communication/SendSmsMessageJob.php`
  - `app/Jobs/Communication/SendInAppNotificationJob.php`
- Generic notification payload:
  - `app/Notifications/GenericInAppNotification.php`

## Sub-phases

### 14.1 Email system

- Email channel contract + default Laravel mail provider implemented.
- Uses `Mail::raw` with centralized sender config from `config/communication.php`.

### 14.2 WhatsApp integration

- WhatsApp channel contract + open-source webhook provider implemented.
- Suitable for n8n/self-hosted automation workers.

### 14.3 SMS

- SMS channel contract + open-source webhook provider implemented.
- Shares the same future-swap strategy as WhatsApp.

### 14.4 Notification system

- In-app notification channel contract + database provider implemented.
- Uses queueable `GenericInAppNotification` through Laravel database notifications.

## Provider swap strategy

To replace any channel backend:

1. Implement the respective channel interface.
2. Add provider key/config in `config/communication.php`.
3. Update binding switch in `AppServiceProvider`.

No upstream controller/service rewrite is required.
