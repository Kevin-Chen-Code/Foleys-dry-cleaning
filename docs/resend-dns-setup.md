# Foley's List Resend DNS setup

The Resend request log reported HTTP 403: **Domain not verified: Verify
foleys.com.au or update your from domain.** The domain has now been added to the
Resend account, but DNS verification is still required.

Open the prepared domain setup page:
https://resend.com/domains/add/94b10747-4ff3-4685-9234-bab63933ce65

Add these records at the DNS provider for `foleys.com.au`. Names below are relative
to the domain. If the DNS provider expects a full hostname, append `.foleys.com.au`
once. Use automatic/default TTL.

| Type | Name | Value |
| --- | --- | --- |
| TXT | `resend._domainkey` | `p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCw/eG0Z4jePoivrvmIhzFI+0Y+aNjV1dFx7ELNH18//7hKeiKsWLEOZ5odzcyWPoVtz5vFJ4t73y6pg3icNhEuFpuFCcrErdn0KnzO84MsN3P0C6xz1YCU8Ez9vXDlGct0p0mnuz+cpZ0FB4iawIcD/9/cW0WPemfj6+2FT803pwIDAQAB` |
| CNAME | `rsend` | `rsend-apne1.forge.rmta.net` |
| CNAME | `send` | `send.forge.rmta.net` |

Keep existing mail-provider records. These records use their own hostnames and
do not require replacing the domain's existing MX or root SPF records. If a
record already exists at one of these hostnames, check that conflict before
changing it. If the provider supports proxying CNAME records, use DNS-only mode.

Once the records are saved, return to Resend and click **I've added the records**.
Wait for sending verification to complete. Leave **Enable Receiving** off; this
portal only needs outgoing email.

On Render, `EMAIL_FROM` can remain `kevinchen@foleys.com.au`. The existing sending
API key must allow this domain (or all domains). Localhost needs its own
`RESEND_API_KEY` and `EMAIL_FROM`; Render's variables do not configure the local
PHP process. Keep the API key in environment variables or the ignored
`config/app_local.php`, never in this document or Git.

After deployment and verification, go to **Weekly report → Email settings**,
save the nominated mailbox, and press **Send test email**. Confirm delivery in
both the mailbox and Resend's Emails page before testing a real request.

These DNS values are public domain-verification records, not API credentials.
