# VoIP.ms Webhook Monitor

A PHP webhook handler that monitors VoIP.ms (or compatible status page) incidents and sends relevant alerts to Slack. Designed for teams using New York servers who need real-time notifications about service disruptions.

## Features

- **Location-based filtering**: Alerts only on New York server incidents
- **Service-wide detection**: Catches platform-wide issues that affect all users
- **Critical incident alerts**: Notifies on critical severity regardless of location
- **Maintenance notifications**: Alerts on scheduled maintenance for your region
- **Rich Slack messages**: Color-coded notifications with full incident details
- **Comprehensive logging**: Debug-friendly logs for troubleshooting

## Quick Start

1. Upload `webhook.php` to your web server
2. Configure your Slack bot token
3. Point your status page webhook to `https://yourdomain.com/webhook.php`
4. Test using `test.html`

See [SETUP_GUIDE.md](SETUP_GUIDE.md) for detailed instructions.

## Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   VoIP.ms       │     │   webhook.php   │     │     Slack       │
│   Status Page   │────>│   (Your Server) │────>│   #alerts       │
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                       │
        │                       ▼
        │               ┌─────────────────┐
        │               │  webhook.log    │
        │               └─────────────────┘
        │
        ▼
   Webhook Events:
   - incident
   - component
   - maintenance
```

## Alert Logic

The webhook processes incoming events through multiple detection layers:

```
Incoming Webhook
       │
       ▼
┌──────────────────────────────────────────────────┐
│ 1. New York Detection                            │
│    - Check incident name for NY keywords         │
│    - Check incident updates for NY mentions      │
│    - Check affected_components array             │
└──────────────────────────────────────────────────┘
       │ Not matched
       ▼
┌──────────────────────────────────────────────────┐
│ 2. Service-Wide Detection                        │
│    - Check for platform-wide keywords            │
│    - Check for infrastructure keywords           │
│    - Check for critical severity                 │
└──────────────────────────────────────────────────┘
       │ Not matched
       ▼
┌──────────────────────────────────────────────────┐
│ 3. Ignore & Log                                  │
│    - Log as "not relevant to our service"        │
│    - Return 200 OK (acknowledge receipt)         │
└──────────────────────────────────────────────────┘
```

## Technical Reference

### Configuration

| Constant | Description | Example |
|----------|-------------|---------|
| `SLACK_BOT_TOKEN` | Slack Bot OAuth token | `xoxb-123456-789...` |
| `SLACK_CHANNEL` | Target Slack channel | `#alerts` or `C1234567890` |
| `ALLOWED_IPS` | IP whitelist (optional) | `['1.2.3.4', '5.6.7.8']` |

### Functions

#### Detection Functions

| Function | Purpose | Returns |
|----------|---------|---------|
| `isNewYorkRelated($text)` | Check if text contains NY keywords | `bool` |
| `isServiceWideIncident($data)` | Check if incident affects all users | `bool` |
| `hasNewYorkComponent($data)` | Check affected_components for NY | `bool` |
| `isAllowedIP()` | Verify request IP against whitelist | `bool` |

#### Formatting Functions

| Function | Purpose | Returns |
|----------|---------|---------|
| `formatIncident($data, $isServiceWide)` | Format incident for Slack | `array` |
| `formatComponent($data)` | Format component update for Slack | `array` |
| `formatMaintenance($data)` | Format maintenance for Slack | `array` |

#### Utility Functions

| Function | Purpose | Returns |
|----------|---------|---------|
| `sendToSlack($blocks, $color)` | Send message to Slack | `bool` |
| `logMessage($message)` | Write to webhook.log | `void` |

### New York Keywords

```php
$keywords = [
    'new york', 'newyork', 'new-york', 'new_york',
    'nyc', 'ny server', 'ny-server',
    'ny-1', 'ny-2', 'ny-3', 'ny-4', 'ny-5',
    'ny1', 'ny2', 'ny3', 'ny4', 'ny5',
    'newyork1', 'newyork2', 'newyork3',
    'us-east', 'us east', 'east-us', 'east coast'
];
```

### Service-Wide Keywords

```php
$serviceWideKeywords = [
    'all server', 'all service', 'platform wide', 'platform-wide',
    'service wide', 'service-wide', 'global outage', 'major outage',
    'complete outage', 'total outage', 'network wide', 'network-wide',
    'all customer', 'all user', 'everyone', 'entire network',
    'all location', 'all datacenter', 'all data center',
    'ddos', 'dos attack', 'security incident',
    'billing', 'payment', 'portal', 'control panel', 'customer portal',
    'api outage', 'api down', 'authentication', 'login issue',
    'sip registration', 'registration issue', 'dns issue', 'dns outage'
];
```

### Webhook Payload Formats

#### Incident Payload

```json
{
  "incident": {
    "id": "abc123",
    "name": "New York Server Connection Issues",
    "status": "INVESTIGATING",
    "impact": "major",
    "created_at": "2024-01-15T10:30:00Z",
    "url": "https://status.voip.ms/incidents/abc123",
    "components": [
      {"name": "NY1 SIP Server", "status": "degraded_performance"}
    ],
    "incident_updates": [
      {
        "body": "We are investigating the issue.",
        "status": "INVESTIGATING",
        "created_at": "2024-01-15T10:30:00Z"
      }
    ]
  }
}
```

#### Component Payload

```json
{
  "component": {
    "id": "comp123",
    "name": "NY1 - SIP Server",
    "status": "DEGRADEDPERFORMANCE",
    "created_at": "2024-01-15T10:30:00Z"
  },
  "component_update": {
    "new_status": "DEGRADEDPERFORMANCE",
    "created_at": "2024-01-15T10:30:00Z"
  },
  "page": {
    "url": "https://status.voip.ms"
  }
}
```

#### Maintenance Payload

```json
{
  "maintenance": {
    "id": "maint123",
    "name": "New York Server Maintenance",
    "status": "SCHEDULED",
    "scheduled_for": "2024-01-16T02:00:00Z",
    "scheduled_until": "2024-01-16T04:00:00Z",
    "url": "https://status.voip.ms/maintenance/maint123",
    "maintenance_updates": [
      {
        "body": "Scheduled maintenance window for NY servers.",
        "created_at": "2024-01-15T10:00:00Z"
      }
    ]
  }
}
```

### Status Codes

| HTTP Code | Meaning | When |
|-----------|---------|------|
| `200` | Success | Webhook received and processed |
| `400` | Bad Request | Invalid JSON payload |
| `403` | Forbidden | IP not in whitelist |
| `500` | Server Error | Unexpected exception |

### Incident Statuses

| Status | Color | Emoji |
|--------|-------|-------|
| `INVESTIGATING` | Blue | :large_blue_circle: |
| `IDENTIFIED` | Blue | :large_blue_circle: |
| `MONITORING` | Yellow | :yellow_circle: |
| `RESOLVED` | Green | :green_circle: |

### Impact Levels

| Impact | Color | Emoji |
|--------|-------|-------|
| `critical` | Red | :red_circle: |
| `major` | Red | :red_circle: |
| `minor` | Yellow | :yellow_circle: |
| `none` | Blue | :large_blue_circle: |

### Component Statuses

| Status | Color | Emoji |
|--------|-------|-------|
| `MAJOROUTAGE` | Red | :red_circle: |
| `PARTIALOUTAGE` | Orange | :orange_circle: |
| `DEGRADEDPERFORMANCE` | Yellow | :yellow_circle: |
| `OPERATIONAL` | Green | :green_circle: |
| `UNDERMAINTENANCE` | Blue | :large_blue_circle: |

### Maintenance Statuses

| Status | Color | Emoji |
|--------|-------|-------|
| `SCHEDULED` | Blue | :large_blue_circle: |
| `INPROGRESS` / `IN_PROGRESS` | Orange | :orange_circle: |
| `VERIFYING` | Yellow | :yellow_circle: |
| `COMPLETED` | Green | :green_circle: |

## Slack Message Format

Messages use Slack's Block Kit format with:

- **Header block**: Emoji + alert type
- **Section block with fields**: Incident details (name, status, impact, time)
- **Section block**: Latest update text
- **Section block**: Link to full details
- **Attachment**: Color indicator bar

## Log Format

```
[YYYY-MM-DD HH:MM:SS] Message
```

Example log entries:
```
[2024-01-15 10:30:45] Received webhook from 1.2.3.4
[2024-01-15 10:30:45] New York incident detected (name match): NY Server Issues
[2024-01-15 10:30:45] Slack notification sent successfully
[2024-01-15 10:35:22] Received webhook from 1.2.3.4
[2024-01-15 10:35:22] Not relevant to our service, ignoring
```

## File Structure

```
.
├── README.md           # This file
├── SETUP_GUIDE.md      # Step-by-step setup instructions
├── webhook.php         # Main webhook handler
├── test.html           # Browser-based testing interface
└── webhook.log         # Auto-generated log file (after first webhook)
```

## Requirements

| Requirement | Minimum Version |
|-------------|-----------------|
| PHP | 7.4+ |
| PHP Extensions | curl, json |
| SSL Certificate | Required (HTTPS) |
| Slack Bot Scope | `chat:write` |

## Security Considerations

1. **HTTPS Required**: Webhook URL must use HTTPS
2. **Token Security**: Never commit Slack bot tokens to version control
3. **IP Whitelisting**: Optionally restrict to known webhook source IPs
4. **File Permissions**: Set webhook.php to 644
5. **Log Protection**: Consider protecting webhook.log from public access

### Protecting the Log File

Add to `.htaccess`:
```apache
<Files "webhook.log">
    Order allow,deny
    Deny from all
</Files>
```

Or move the log outside public_html:
```php
$logFile = dirname(__DIR__) . '/logs/webhook.log';
```

## Extending the Script

### Add a New Location

To monitor a different location (e.g., Los Angeles):

```php
function isLosAngelesRelated($text) {
    if (empty($text)) return false;
    $text = strtolower($text);
    $keywords = ['los angeles', 'la server', 'la-1', 'la1', 'us-west'];
    foreach ($keywords as $keyword) {
        if (strpos($text, $keyword) !== false) return true;
    }
    return false;
}
```

### Add Email Notifications

```php
function sendEmail($subject, $body) {
    $to = 'alerts@yourcompany.com';
    $headers = 'From: webhook@yourdomain.com';
    mail($to, $subject, $body, $headers);
}
```

### Add PagerDuty Integration

```php
function sendToPagerDuty($incident) {
    $url = 'https://events.pagerduty.com/v2/enqueue';
    $data = [
        'routing_key' => 'your-integration-key',
        'event_action' => 'trigger',
        'payload' => [
            'summary' => $incident['name'],
            'severity' => $incident['impact'] === 'critical' ? 'critical' : 'warning',
            'source' => 'voip-webhook'
        ]
    ];
    // Send via curl...
}
```

## Testing

### Using test.html

1. Open `test.html` in a browser
2. Enter your webhook URL
3. Select a test scenario
4. Click "Send Test Webhook"
5. Check Slack and webhook.log

### Using cURL

```bash
# Test NY incident
curl -X POST "https://yourdomain.com/webhook.php" \
  -H "Content-Type: application/json" \
  -d '{"incident":{"name":"New York Server Test","status":"INVESTIGATING","impact":"major"}}'

# Test service-wide incident
curl -X POST "https://yourdomain.com/webhook.php" \
  -H "Content-Type: application/json" \
  -d '{"incident":{"name":"Test","status":"INVESTIGATING","impact":"major","incident_updates":[{"body":"All servers affected"}]}}'
```

## Troubleshooting

| Problem | Solution |
|---------|----------|
| No Slack messages | Check bot token, verify bot is in channel |
| 403 Forbidden | Check ALLOWED_IPS configuration |
| 400 Bad Request | Verify JSON payload format |
| Alerts not triggering | Check keywords match, review webhook.log |
| Duplicate alerts | Check if webhook is registered multiple times |

## License

MIT License - Feel free to modify and use in your projects.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly using test.html
5. Submit a pull request
