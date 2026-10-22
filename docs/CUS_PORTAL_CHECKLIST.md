# Customer portal — CUS-* coverage

Maps proposal inventory IDs to this codebase. Sandbox/conditional items use agency records or simulated gateways — **not** live insurer/TIRA confirmations.

| ID | Capability | Status in this build |
|---|---|---|
| CUS-01 | Registration / enrolment | Done — `POST /portal/auth/register`, `/portal/register` |
| CUS-02 | Secure login | Done — shared `/auth/login` → `/portal` |
| CUS-03 | Identity checks | Done — KYC + identity status on profile |
| CUS-04 | OTP verification | Done (sandbox) — OTP returned in API response |
| CUS-05 | Password recovery | Done (sandbox token) — `/portal/forgot-password` |
| CUS-06 | View/update profile | Done — `/portal/profile` |
| CUS-07 | Browse products | Done — `/portal/products` |
| CUS-08 | Product descriptions | Done — disclosures / eligibility from product versions |
| CUS-09 | Quotation / product enquiry | Done — service request type `quotation` |
| CUS-10 | View policies | Done |
| CUS-11 | Status / cover / dates | Done |
| CUS-12 | Premium / due amounts | Done — agency-held amounts + premium instructions |
| CUS-13 | View documents | Done — metadata list |
| CUS-14 | Download documents | Done — placeholder text download (no object storage yet) |
| CUS-15 | Initiate premium payment | Done — sandbox gateway |
| CUS-16 | Transaction status | Done |
| CUS-17 | Receipt / acknowledgement | Done — sandbox receipt number |
| CUS-18 | Transaction history | Done |
| CUS-19 | Start FNOL | Done |
| CUS-20 | Structured incident form | Done — policy, location, description, urgency |
| CUS-21 | Claim evidence upload | Done — metadata register (binary deferred) |
| CUS-22 | Submission acknowledgement | Done |
| CUS-23 | Claim status / milestones | Done — customer-visible timeline |
| CUS-24 | Claim progress updates | Done — notifications + timeline |
| CUS-25 | Renewal reminders | Done — renewals list + seeded notification |
| CUS-26 | Renewal enquiry | Done — service request type `renewal` |
| CUS-27 | Support tickets | Done — `/portal/support` |
| CUS-28 | Track request status | Done |
| CUS-29 | Notifications | Done — in-app |
| CUS-30 | Communication history | Done — `/portal/communications` |
| CUS-31 | Push / SMS | Stub — channel field `email_sandbox` / `sms_sandbox`; no live provider |

## Demo

- Customer: `customer@demo.wabantu.local` / `Password1!`
- Register: `/portal/register`
- Forgot password: `/portal/forgot-password`
