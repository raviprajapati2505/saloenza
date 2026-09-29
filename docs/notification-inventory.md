# Email & SMS / WhatsApp notification inventory

| # | Channel | Message | Trigger | Notification preference key | In Notification Preferences? | Recipients |
|---|---------|---------|---------|------------------------------|------------------------------|------------|
| 1 | Email | Appointment confirmation | Action (booking created / public book) | `appointment_confirmation` | Yes | Customer |
| 2 | Email | Appointment reminder | Cron (`appointments:send-reminders`) | `appointment_reminder` (+ `reminder_hours_before`) | Yes | Customer |
| 3 | Email | Appointment cancellation | Action (status → cancelled) | `booking_cancellation` | Yes | Customer |
| 4 | Email | Staff assignment alert | Action (booking created) | `staff_assignment_alert` | Yes | Staff |
| 5 | Email | Outstanding payment reminder | Action (manual from visit) **or** Cron | Manual: none · Auto: `payment_outstanding_reminder` (+ `outstanding_overdue_days`) | Yes (auto only) · No (manual) | Customer |
| 6 | Email | Birthday wish | Cron (occasion wishes) | `birthday_wishes` (+ `birthday_offer_text`) | Yes | Customer |
| 7 | Email | Anniversary wish | Cron (occasion wishes) | `anniversary_wishes` (+ `anniversary_offer_text`) | Yes | Customer |
| 8 | Email | Subscription renewal reminder | Cron (`subscriptions:send-renewal-reminders`) | `subscription_renewal_reminder` | Yes | Salon owner |
| 9 | Email | Salon welcome / temp password | Action (admin onboarding activate) | — | No | Salon owner |
| 10 | Email | Password reset OTP | Action (forgot password) | — | No | User (email) |
| 11 | SMS → WhatsApp | Appointment confirmation | Action (booking created) | `appointment_confirmation` | Yes | Customer |
| 12 | SMS → WhatsApp | Appointment reminder | Cron | `appointment_reminder` | Yes | Customer |
| 13 | SMS → WhatsApp | Appointment cancellation | Action (status → cancelled) | `booking_cancellation` | Yes | Customer |
| 14 | SMS → WhatsApp | Outstanding payment reminder | Action **or** Cron | Manual: none · Auto: `payment_outstanding_reminder` | Yes (auto only) · No (manual) | Customer |
| 15 | SMS → WhatsApp | Password reset OTP | Action (forgot password by phone) | — | No | User (phone) |

## Notes

- **Trigger:** **Action** = user/API event · **Cron** = scheduled job
- **In Notification Preferences?** = gated by Settings → Notification Preferences (`—` / No = always sent when triggered)
- Rows **11–15** are former SMS paths, reserved for future **WhatsApp** integration
- Email delivery uses **Brevo API** via `.env` (`BREVO_API_KEY`, `MAIL_MAILER=brevo`)
