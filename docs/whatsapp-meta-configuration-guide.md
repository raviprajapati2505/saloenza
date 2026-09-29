# Meta WhatsApp Cloud API — configuration guide (before coding)

**Decision:** Option A — Meta WhatsApp Cloud API (direct)  
**Phase 1:** Qatar (+974)  
**First messages:** Appointment confirmation, reminder, cancellation  

Complete **all steps in this guide** before we write application code.

Official docs: https://developers.facebook.com/docs/whatsapp/cloud-api/get-started

---

## What you will have at the end

| Item | Where you get it | Used for |
|------|------------------|----------|
| Meta Business account | business.facebook.com | Company ownership |
| WhatsApp Business Account (WABA) | Meta Business Suite | WhatsApp messaging account |
| Business phone number | Meta / your SIM or virtual number | Sender number customers see |
| Phone Number ID | Meta Developer / WhatsApp → API Setup | API sends “from” this ID |
| WhatsApp Business Account ID (WABA ID) | Same | Account reference |
| Permanent Access Token | Meta System User + token | App authentication (not temporary test token) |
| App ID / App Secret | developers.facebook.com | App security / webhooks |
| Approved message templates | WhatsApp Manager → Message templates | Confirmation / reminder / cancel |
| Webhook verify token + callback URL | You choose + later Saloenza URL | Delivery status (can wait until coding) |

Save these in a password manager. Later we put them in `.env` (never in git).

---

## Phase 0 — Prepare (business)

### 0.1 Decide

- [ ] Company legal name (same as trade licence)
- [ ] Display name customers will see on WhatsApp (e.g. `Saloenza` or your brand)
- [ ] Phase 1 country: **Qatar**
- [ ] Sender model: **one shared number** for all salons (MVP)
- [ ] Admin person who can complete Meta verification (has company docs)

### 0.2 Documents ready (for Meta Business verification)

Typical asks (Meta may vary):

- [ ] Trade licence / commercial registration (Qatar CR if business is in Qatar)
- [ ] Proof of address
- [ ] Domain / website (e.g. saloenza.com)
- [ ] Business email on that domain (preferred)
- [ ] Phone that can receive OTP

### 0.3 Choose the WhatsApp number

Options:

| Option | Notes |
|--------|--------|
| **New Qatar number (+974)** | Best for Phase 1 customers in Qatar |
| Existing company mobile | Must be able to receive SMS/voice OTP once; then it becomes WhatsApp Business API number (cannot stay on normal WhatsApp app) |

**Important:** A number on the normal WhatsApp / WhatsApp Business **app** must be migrated / released before Cloud API use. Prefer a **fresh number** dedicated to Saloenza notifications.

---

## Phase 1 — Meta Business account

1. Go to [business.facebook.com](https://business.facebook.com/)
2. Create a **Business Account** (or use existing).
3. Add yourself as **Admin**.
4. Open **Business settings** → **Security Centre** / **Business Info**.
5. Start **Business verification**:
   - Submit legal name, address, documents
   - Wait for Meta review (can take 1–several days)
6. Until verified, sending limits stay very low — treat verification as **blocking** for real launch.

**Checkpoint:** Business shows as **Verified**.

---

## Phase 2 — Meta Developer App

1. Go to [developers.facebook.com](https://developers.facebook.com/)
2. **My Apps** → **Create App**
3. Choose use case that includes **WhatsApp** (or add WhatsApp product later)
4. Select your **Business Portfolio / Business Account**
5. Inside the app:
   - Note **App ID**
   - Note **App Secret** (Settings → Basic) — keep secret

**Checkpoint:** App exists and is linked to your Business.

---

## Phase 3 — Add WhatsApp product + WABA

1. In the app dashboard: **Add product** → **WhatsApp** → Set up  
2. Meta creates / links a **WhatsApp Business Account (WABA)**  
3. Open **WhatsApp** → **API Setup** (left menu)  
4. Note:
   - Temporary access token (testing only — expires)
   - **Phone Number ID** (after you add a number)
   - **WhatsApp Business Account ID**

Also open [WhatsApp Manager](https://business.facebook.com/wa/manage/) for templates and phone settings.

**Checkpoint:** WhatsApp product visible; WABA ID known.

---

## Phase 4 — Add / register phone number

1. WhatsApp → **API Setup** → **Add phone number**  
   (or WhatsApp Manager → Phone numbers → Add)
2. Enter display name (must follow Meta naming rules — real business name)
3. Verify number with SMS / voice OTP
4. Complete any **display name review** if prompted
5. Copy **Phone Number ID** from API Setup

**Qatar tip:** Use +974 if Phase 1 is Qatar-only.

**Checkpoint:** Number status is connected / available; you can send a **test** message from API Setup to your personal WhatsApp.

---

## Phase 5 — Test with Meta’s own tester (no Saloenza code yet)

1. WhatsApp → **API Setup**
2. Add a **test recipient** WhatsApp number (your phone)
3. Send the sample “hello” / template message from the console
4. Confirm it arrives

If this fails, **do not start coding** — fix Meta setup first.

**Checkpoint:** You received a WhatsApp message from the business number.

---

## Phase 6 — Create permanent access token (required for production)

Temporary tokens expire. For Saloenza you need a **long-lived / permanent** token via System User.

1. [business.facebook.com](https://business.facebook.com/) → **Business settings**
2. **Users** → **System users** → **Add**
3. Create Admin system user (e.g. `saloenza-whatsapp`)
4. **Add assets**:
   - Your **App**
   - Your **WhatsApp Business Account**
5. Generate token:
   - Assign permissions typically including:
     - `whatsapp_business_messaging`
     - `whatsapp_business_management`
6. Copy token **once** → store in password manager  
7. You will later put it in `.env` as e.g. `WHATSAPP_ACCESS_TOKEN=...`

**Never commit this token to GitHub.**

**Checkpoint:** System user token generated and stored securely.

---

## Phase 7 — Message templates (must be approved)

Go to: **WhatsApp Manager** → **Message templates** → **Create template**

### 7.1 Rules (plain language)

- Templates must be **approved by Meta** before automated sends  
- Use **Utility** category for booking messages  
- Keep language transactional (no ads / discounts in these three)  
- For Qatar: create **English** first; add **Arabic** variants soon after  

### 7.2 Suggested Phase 1 templates

Use your own wording; keep placeholders clear.

#### Template 1 — Appointment confirmation  
- **Name:** `appointment_confirmation`  
- **Category:** Utility  
- **Language:** English (and later Arabic)  
- **Body example:**

```text
Hello {{1}}, your appointment at {{2}} is confirmed for {{3}} ({{4}}). Ref: {{5}}.
```

Example variables: customer name, salon name, date, time, booking id.

#### Template 2 — Appointment reminder  
- **Name:** `appointment_reminder`  
- **Category:** Utility  

```text
Reminder: your appointment at {{1}} is on {{2}} at {{3}}. See you soon.
```

#### Template 3 — Appointment cancellation  
- **Name:** `appointment_cancellation`  
- **Category:** Utility  

```text
Your appointment at {{1}} on {{2}} at {{3}} has been cancelled. Contact the salon if you need help.
```

### 7.3 Submit & wait

- [ ] Submit all 3 templates  
- [ ] Status becomes **Approved** (not Pending / Rejected)  
- [ ] If rejected: read reason, fix wording, resubmit  

**Checkpoint:** All 3 templates = **Approved**.

---

## Phase 8 — Quality & limits (know before launch)

| Topic | What to do |
|-------|------------|
| Messaging limit | New numbers start with a daily limit; rises with good quality |
| Quality rating | Avoid spam; only message opted-in customers |
| Opt-in | When saving WhatsApp number, customer agrees to appointment updates |
| Prefer `whatsapp` field | Do not blast every phone; use WhatsApp number when present |

---

## Phase 9 — Webhooks (can finish during coding)

Needed for delivery receipts / failures (recommended, not blocking for first manual API test).

1. In Meta App → WhatsApp → **Configuration** → Webhooks  
2. Later Saloenza will expose a public HTTPS URL, e.g. `https://app.saloenza.com/api/v1/webhooks/whatsapp`  
3. You choose a **Verify Token** (random secret string)  
4. Subscribe to fields such as: `messages`  

For local development, use a tunnel (ngrok / Cloudflare tunnel) when we code.

**Checkpoint (later):** Webhook verified in Meta dashboard.

---

## Phase 10 — Collect credentials checklist (hand to developer)

Fill this and keep offline / password manager:

```text
META_APP_ID=
META_APP_SECRET=
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=          # permanent system user token
WHATSAPP_API_VERSION=v21.0      # or current version shown in Meta docs
WHATSAPP_SENDER_DISPLAY=        # e.g. Saloenza
WHATSAPP_DEFAULT_COUNTRY=QA

# Template names (exact names from WhatsApp Manager)
WHATSAPP_TEMPLATE_CONFIRMATION=appointment_confirmation
WHATSAPP_TEMPLATE_REMINDER=appointment_reminder
WHATSAPP_TEMPLATE_CANCELLATION=appointment_cancellation
WHATSAPP_TEMPLATE_LANG=en

# Webhook (when ready)
WHATSAPP_WEBHOOK_VERIFY_TOKEN=
```

**Also note:**

- [ ] Business verification status: _______________  
- [ ] Phone number (E.164): +974… _______________  
- [ ] Templates approved (EN): Yes / No  
- [ ] Templates approved (AR): Yes / No / Later  
- [ ] Test message received on personal WhatsApp: Yes / No  

---

## Suggested order of work (calendar)

| Day | Task |
|-----|------|
| Day 1 | Business account + start verification + create Developer App |
| Day 1–2 | Add WhatsApp + register Qatar number + send Meta test message |
| Day 2 | Create System User permanent token |
| Day 2–3 | Create & submit 3 utility templates (EN; AR if ready) |
| Day 3–7 | Wait for business verification + template approval |
| When all green | Tell developer → start Saloenza coding |

---

## Do / Don’t

**Do**

- Use a dedicated WhatsApp Business API number  
- Store tokens securely  
- Start with 3 utility templates only  
- Test with real Qatar numbers  

**Don’t**

- Put tokens in chat, email, or git  
- Start coding before test message + approved templates  
- Use Marketing category for booking messages  
- Try to use the same number on WhatsApp personal app and API  

---

## When you are ready for coding

Come back when these are true:

1. Business verification done (or at least number + test send works)  
2. Permanent access token ready  
3. Phone Number ID + WABA ID copied  
4. Three templates **Approved**  
5. You received a successful test message from Meta console  

Then we will:

- Add `.env` keys  
- Build `WhatsAppDeliveryService`  
- Hook confirmation / reminder / cancellation (inventory rows 11–13)  
- Prefer customer `whatsapp` field + notification preferences  

---

*Related docs:*  
- `docs/whatsapp-pricing-and-decision-guide.md`  
- `docs/notification-inventory.md`
