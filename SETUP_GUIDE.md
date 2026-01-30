# VoIP.ms Webhook Monitor - Setup Guide

## Quick Setup (5 minutes)

### Step 1: Create a Slack App

1. Go to https://api.slack.com/apps
2. Click **"Create New App"** > **"From scratch"**
3. Name it "VoIP Status Monitor" and select your workspace
4. In the left sidebar, click **"OAuth & Permissions"**
5. Scroll to **"Bot Token Scopes"** and add:
   - `chat:write` (required)
6. Scroll up and click **"Install to Workspace"**
7. Copy the **"Bot User OAuth Token"** (starts with `xoxb-`)
8. Invite the bot to your alerts channel:
   ```
   /invite @VoIP Status Monitor
   ```

### Step 2: Upload to cPanel

1. Log into your cPanel
2. Open **"File Manager"**
3. Navigate to `public_html` (or your preferred directory)
4. Click **"Upload"** and upload `webhook.php`
5. Right-click the file and select **"Edit"**
6. Update the configuration section:
   ```php
   define('SLACK_BOT_TOKEN', 'xoxb-your-actual-bot-token');
   define('SLACK_CHANNEL', '#alerts');  // or use channel ID like C1234567890
   ```
7. Save the file

### Step 3: Configure the Webhook Source

**For VoIP.ms Status Page:**
1. If VoIP.ms provides webhook subscriptions, configure them to send to:
   ```
   https://yourdomain.com/webhook.php
   ```

**For Instatus / Statuspage.io:**
1. Go to your status page dashboard
2. Navigate to **Webhooks** or **Integrations**
3. Click **"Add Webhook"**
4. Enter your webhook URL: `https://yourdomain.com/webhook.php`
5. Subscribe to: **Incidents**, **Components**, and **Maintenance**
6. Save the webhook

### Step 4: Test It

1. Open `test.html` in your browser (upload it alongside webhook.php, or open locally)
2. Enter your webhook URL
3. Select "NY Incident - Major Outage" and click **Send Test Webhook**
4. Check your Slack channel for the notification
5. Check `webhook.log` in cPanel File Manager for debugging

---

## What Gets Monitored

The webhook monitors and alerts on:

| Event Type | Triggers Alert |
|------------|----------------|
| New York server incidents | Yes |
| New York component updates | Yes |
| New York scheduled maintenance | Yes |
| Service-wide/platform-wide incidents | Yes |
| DDoS attacks | Yes |
| Billing/payment system issues | Yes |
| SIP registration problems | Yes |
| DNS/authentication issues | Yes |
| Critical severity incidents (any location) | Yes |
| Other locations (London, LA, Chicago, etc.) | No |

### New York Detection Keywords

The script detects New York references using these keywords:
- `new york`, `newyork`, `new-york`, `new_york`
- `nyc`, `ny server`, `ny-server`
- `ny-1` through `ny-5`, `ny1` through `ny5`
- `newyork1`, `newyork2`, `newyork3`
- `us-east`, `us east`, `east-us`, `east coast`

### Service-Wide Detection Keywords

These keywords trigger alerts regardless of location:
- `all server`, `all service`, `platform wide`, `service wide`
- `global outage`, `major outage`, `complete outage`
- `ddos`, `dos attack`, `security incident`
- `billing`, `payment`, `portal`, `control panel`
- `sip registration`, `dns issue`, `authentication`, `login issue`

---

## Troubleshooting

### No Slack messages appearing?

**Check the log file:**
```
public_html/webhook.log
```

**Common issues:**

1. **Bot token incorrect**
   - Must start with `xoxb-`
   - No extra spaces or quotes around the token

2. **Bot not in channel**
   - Go to Slack and type: `/invite @YourBotName`

3. **Wrong channel name**
   - Use `#channel-name` with the # symbol
   - Or use channel ID like `C1234567890`

4. **PHP curl not enabled**
   - Contact your hosting provider to enable PHP curl extension

### Webhook not receiving data?

1. **Check webhook URL format**
   - Must be HTTPS (not HTTP)
   - Example: `https://yourdomain.com/webhook.php`

2. **Check file permissions**
   - Right-click `webhook.php` in cPanel File Manager
   - Click "Change Permissions"
   - Set to 644

3. **Check webhook.log**
   - Look for incoming request logs
   - Check for error messages

### Testing manually with cURL

```bash
# Set your webhook URL
WEBHOOK_URL="https://yourdomain.com/webhook.php"

# Test NY incident
curl -X POST "$WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -d '{"incident":{"name":"New York Server Test","status":"INVESTIGATING","impact":"major","incident_updates":[{"body":"Test message"}]}}'

# Test service-wide incident
curl -X POST "$WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -d '{"incident":{"name":"Platform Issue","status":"INVESTIGATING","impact":"major","incident_updates":[{"body":"All servers experiencing issues"}]}}'

# Test maintenance
curl -X POST "$WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -d '{"maintenance":{"name":"NY Server Maintenance","status":"SCHEDULED","scheduled_for":"2024-01-15T02:00:00Z","scheduled_until":"2024-01-15T04:00:00Z"}}'
```

---

## Security Recommendations

1. **Always use HTTPS** for your webhook URL
2. **Never share your Slack bot token**
3. **Add IP whitelist** if you know the source IPs:
   ```php
   define('ALLOWED_IPS', ['1.2.3.4', '5.6.7.8']);
   ```
4. **Set file permissions** to 644 for webhook.php
5. **Protect webhook.log** - consider moving it outside public_html or adding .htaccess protection

---

## Customization

### Add or modify New York keywords

Edit the `isNewYorkRelated()` function in `webhook.php`:

```php
$keywords = [
    'new york', 'newyork', 'new-york', 'new_york',
    'nyc', 'ny server', 'ny-server',
    // Add your custom keywords here
    'your-custom-keyword',
];
```

### Add or modify service-wide keywords

Edit the `isServiceWideIncident()` function in `webhook.php`:

```php
$serviceWideKeywords = [
    'all server', 'all service', 'platform wide',
    // Add your custom keywords here
    'your-custom-keyword',
];
```

### Change Slack message colors

Edit the color codes in `formatIncident()`, `formatComponent()`, or `formatMaintenance()`:

```php
$color = '#d63031';  // Red for critical
$color = '#fdcb6e';  // Yellow for warning
$color = '#00b894';  // Green for resolved
$color = '#0984e3';  // Blue for info
```

### Disable maintenance alerts

To stop alerting on maintenance, find this section in the main execution block and comment it out:

```php
// Check for maintenance - alert if NY related
elseif (isset($data['maintenance'])) {
    // Comment out or remove the alerting logic
}
```

---

## File Structure

```
public_html/
├── webhook.php       # Main webhook handler
├── webhook.log       # Auto-generated log file
└── test.html         # Optional: testing interface
```

---

## Viewing Logs

### Via cPanel File Manager:
1. Open **File Manager**
2. Navigate to your webhook directory
3. Right-click `webhook.log`
4. Select **"View"** or **"Edit"**

### Via SSH (if enabled):
```bash
# View last 50 lines
tail -50 ~/public_html/webhook.log

# Follow log in real-time
tail -f ~/public_html/webhook.log
```

### Log format:
```
[2024-01-15 10:30:45] Received webhook from 1.2.3.4
[2024-01-15 10:30:45] New York incident detected (name match): NY Server Issues
[2024-01-15 10:30:45] Slack notification sent successfully
```

---

## Requirements

- PHP 7.4 or higher
- PHP curl extension enabled
- HTTPS/SSL certificate
- Slack workspace with bot permissions

---

## Example Slack Notifications

### Major Incident
```
[Red indicator]
New York Server Incident

Incident: New York Server Connection Issues
Status: INVESTIGATING
Impact: MAJOR
Created: 2024-01-15T10:30:00Z

Latest Update:
We are investigating connection issues affecting our New York datacenter.

[View Incident Details]
```

### Service-Wide Alert
```
[Red indicator]
Service-Wide Alert

Incident: Platform Connectivity Issues
Status: INVESTIGATING
Impact: MAJOR
Created: 2024-01-15T10:30:00Z

Latest Update:
All servers are experiencing connectivity issues.

[View Incident Details]
```

### Scheduled Maintenance
```
[Blue indicator]
Scheduled Maintenance Alert

Maintenance: New York Server Maintenance
Status: SCHEDULED
Scheduled Start: 2024-01-16T02:00:00Z
Scheduled End: 2024-01-16T04:00:00Z

Details:
Scheduled maintenance window for NY1 and NY2 servers.

[View Maintenance Details]
```

### Resolved Incident
```
[Green indicator]
New York Server Incident

Incident: New York Server Connection Issues
Status: RESOLVED
Impact: MAJOR
Created: 2024-01-15T10:30:00Z

Latest Update:
All services have been restored. Thank you for your patience.

[View Incident Details]
```
