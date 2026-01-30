# Webhook Monitor Setup Guide for cPanel

## Quick Setup (3 minutes)

### Step 1: Get Your Slack Bot Token

1. Go to https://api.slack.com/apps
2. Click **"Create New App"** → **"From scratch"**
3. Name it "Status Monitor" and select your workspace
4. In the left sidebar, click **"OAuth & Permissions"**
5. Scroll to **"Bot Token Scopes"** and add:
   - `chat:write` (required)
6. Scroll up and click **"Install to Workspace"**
7. Copy the **"Bot User OAuth Token"** (starts with `xoxb-`)
8. Go to Slack and invite the bot to your channel:
   - Type `/invite @Status Monitor` in your channel

### Step 2: Upload to cPanel

1. Log into your cPanel
2. Open **"File Manager"**
3. Navigate to `public_html` (or wherever you want)
4. Click **"Upload"** and upload `webhook.php`
5. Right-click the file and select **"Edit"**
6. Update these lines at the top:
   ```php
   define('SLACK_BOT_TOKEN', 'xoxb-your-actual-bot-token');
   define('SLACK_CHANNEL', '#alerts');  // or channel ID
   ```
7. Save the file

### Step 3: Configure Your VoIP.ms (or Status Page) Webhook

**For VoIP.ms:**
1. Log into VoIP.ms account
2. Go to your webhook settings
3. Enter webhook URL: `https://yourdomain.com/webhook.php`
4. Save

**For Status Page (Instatus, etc):**
1. Go to your status page dashboard
2. Navigate to **Webhooks** or **Integrations**
3. Click **"Add Webhook"**
4. Enter your webhook URL: `https://yourdomain.com/webhook.php`
5. Subscribe to: **Incidents**, **Components**, and optionally **Maintenance**
6. Save the webhook

### Step 4: Test It

1. Create a test incident with "New York" in the name
2. Check your Slack channel for the notification
3. Check the log file: `public_html/webhook.log` for debugging

## Troubleshooting

### No Slack messages appearing?

**Check the log file:**
```
public_html/webhook.log
```

**Common issues:**

1. **Bot token incorrect**
   - Make sure it starts with `xoxb-`
   - No extra spaces or quotes

2. **Bot not in channel**
   - Go to Slack and type: `/invite @YourBotName`

3. **Wrong channel name**
   - Use `#channel-name` with the # symbol
   - Or use channel ID like `C1234567890`

4. **PHP curl not enabled**
   - Contact your hosting provider to enable PHP curl extension

### Webhook not receiving data?

1. **Check webhook URL is correct**
   - Should be: `https://yourdomain.com/webhook.php`
   - Must use HTTPS (not HTTP)

2. **Check file permissions**
   - Right-click `webhook.php` in cPanel File Manager
   - Click "Change Permissions"
   - Set to 644

3. **View the log file**
   - Check `webhook.log` in the same directory
   - Look for error messages

4. **Check webhook format**
   - The webhook must send JSON data
   - Common services: VoIP.ms, Instatus, etc.

### Optional: Add IP Whitelist for Extra Security

If you know the IPs that will send webhooks (e.g., VoIP.ms servers), you can add them to the whitelist in the PHP file:

```php
define('ALLOWED_IPS', ['1.2.3.4', '5.6.7.8']);
```

This adds an extra security layer since there's no signature verification.

### Testing the webhook manually

You can test using cURL from your computer:

```bash
# Replace with your actual URL
WEBHOOK_URL="https://yourdomain.com/webhook.php"

# Test payload
PAYLOAD='{"incident":{"name":"New York Server Test","status":"INVESTIGATING","impact":"major","created_at":"2026-01-30T12:00:00Z","incident_updates":[{"body":"Testing webhook"}]}}'

# Send test webhook
curl -X POST "$WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD"
```

You should receive:
- HTTP 200 response
- A Slack notification in your channel
- An entry in `webhook.log`

## Security Notes

- ✅ Always use HTTPS for your webhook URL
- ✅ Never share your Slack bot token
- ✅ Consider adding IP whitelist in the script for extra security
- ✅ Consider restricting file permissions to 644
- ⚠️ Note: VoIP.ms webhooks don't have signature verification, so IP whitelisting is recommended if you know their server IPs

## Customization

### Change New York keywords

Edit this section in `webhook.php`:

```php
$keywords = ['new york', 'newyork', 'ny server', 'nyc', 'new-york', 'ny-1'];
```

### Alert on maintenance too

Find this section and modify:

```php
elseif (isset($data['maintenance'])) {
    // Currently only logs, doesn't alert
    // To enable alerts, uncomment:
    // if (isNewYorkRelated($maintenanceName)) {
    //     $shouldAlert = true;
    //     $message = formatMaintenance($data);
    // }
}
```

### Change Slack message colors

Edit the color codes in the `formatIncident()` and `formatComponent()` functions.

## File Structure

```
public_html/
├── webhook.php       (your webhook script)
└── webhook.log       (auto-generated log file)
```

## Viewing Logs

To view recent logs via cPanel:

1. Open **File Manager**
2. Navigate to your webhook directory
3. Right-click `webhook.log`
4. Select **"View"** or **"Edit"**

Or via SSH (if enabled):
```bash
tail -f ~/public_html/webhook.log
```

## Support

If you encounter issues:

1. Check `webhook.log` for error messages
2. Verify all configuration values are correct
3. Test with a manual curl request (see above)
4. Ensure your hosting supports:
   - PHP 7.4 or higher
   - PHP curl extension
   - HTTPS/SSL

## What Gets Monitored?

The script will send Slack alerts for:

- ✅ Incidents mentioning "New York" in the title
- ✅ Incidents with "New York" in update messages
- ✅ Component updates for components with "New York" in name
- ❌ Maintenance events (logged but not alerted by default)
- ❌ Other servers/locations (ignored completely)

## Example Slack Alerts

**Major Incident:**
```
🔴 New York Server Incident

Incident: New York Server Connection Issues
Status: INVESTIGATING
Impact: MAJOR
Created: 2026-01-30T12:00:00Z

Latest Update:
We are investigating connection issues affecting our New York datacenter.

[View Incident Details]
```

**Resolved:**
```
🟢 New York Server Incident

Incident: New York Server Connection Issues
Status: RESOLVED
Impact: MAJOR
Created: 2026-01-30T12:00:00Z

Latest Update:
All services have been restored.

[View Incident Details]
```
