<?php
/**
 * VoIP.ms Webhook Monitor
 * Reports relevant incidents to Slack
 *
 * Alerts on:
 * - New York server incidents, components, and maintenance
 * - Service-wide/platform-wide incidents affecting all users
 * - Critical severity incidents
 * - Core infrastructure issues (SIP, DNS, billing, authentication)
 *
 * Installation:
 * 1. Upload this file to your cPanel (e.g., public_html/webhook.php)
 * 2. Update the configuration below
 * 3. Use the webhook URL: https://yourdomain.com/webhook.php
 */

// ============================================================================
// CONFIGURATION - UPDATE THESE VALUES
// ============================================================================
define('SLACK_BOT_TOKEN', 'xoxb-your-bot-token-here');
define('SLACK_CHANNEL', '#alerts');

// Optional: Add IP whitelist for extra security (VoIP.ms IPs or leave empty)
// Example: define('ALLOWED_IPS', ['1.2.3.4', '5.6.7.8']);
define('ALLOWED_IPS', []);

// ============================================================================
// CODE - NO NEED TO MODIFY BELOW
// ============================================================================

// Set JSON response header
header('Content-Type: application/json');

// Log function for debugging (optional)
function logMessage($message) {
    $logFile = __DIR__ . '/webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Check if request is from allowed IP (if configured)
function isAllowedIP() {
    if (empty(ALLOWED_IPS)) {
        return true; // No IP restriction if not configured
    }
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($clientIP, ALLOWED_IPS);
}

// Check if text mentions New York
function isNewYorkRelated($text) {
    if (empty($text)) {
        return false;
    }

    $text = strtolower($text);
    $keywords = [
        'new york', 'newyork', 'new-york', 'new_york',
        'nyc', 'ny server', 'ny-server',
        'ny-1', 'ny-2', 'ny-3', 'ny-4', 'ny-5',
        'ny1', 'ny2', 'ny3', 'ny4', 'ny5',
        'newyork1', 'newyork2', 'newyork3',
        'us-east', 'us east', 'east-us', 'east coast'
    ];

    foreach ($keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

// Check if this is a service-wide or critical incident that affects everyone
function isServiceWideIncident($data) {
    $text = '';

    // Gather all relevant text from the incident
    if (isset($data['incident'])) {
        $incident = $data['incident'];
        $text .= ' ' . ($incident['name'] ?? '');
        $text .= ' ' . ($incident['impact'] ?? '');

        foreach ($incident['incident_updates'] ?? [] as $update) {
            $text .= ' ' . ($update['body'] ?? '');
        }
    }

    $text = strtolower($text);

    // Keywords indicating service-wide issues
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

    foreach ($serviceWideKeywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return true;
        }
    }

    // Also alert on critical/major impact regardless of keywords
    $impact = strtolower($data['incident']['impact'] ?? '');
    if ($impact === 'critical') {
        return true;
    }

    return false;
}

// Check affected components array for New York
function hasNewYorkComponent($data) {
    $components = [];

    // Check incident's affected components
    if (isset($data['incident']['components'])) {
        $components = array_merge($components, $data['incident']['components']);
    }
    if (isset($data['incident']['affected_components'])) {
        $components = array_merge($components, $data['incident']['affected_components']);
    }

    foreach ($components as $component) {
        $name = is_array($component) ? ($component['name'] ?? '') : $component;
        if (isNewYorkRelated($name)) {
            return true;
        }
    }

    return false;
}

// Send message to Slack
function sendToSlack($blocks, $color) {
    $url = 'https://slack.com/api/chat.postMessage';
    
    $data = [
        'channel' => SLACK_CHANNEL,
        'blocks' => $blocks,
        'attachments' => [
            ['color' => $color, 'text' => '']
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . SLACK_BOT_TOKEN,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode == 200 && isset($result['ok']) && $result['ok']) {
        logMessage("✓ Slack notification sent successfully");
        return true;
    } else {
        $error = isset($result['error']) ? $result['error'] : 'Unknown error';
        logMessage("✗ Slack notification failed: $error");
        return false;
    }
}

// Format incident for Slack
function formatIncident($data, $isServiceWide = false) {
    $incident = $data['incident'];
    $impact = strtolower($incident['impact'] ?? '');
    $status = strtolower($incident['status'] ?? '');

    // Determine emoji and color
    if (in_array($impact, ['critical', 'major']) || strpos($status, 'outage') !== false) {
        $emoji = '🔴';
        $color = '#d63031';
    } elseif ($impact == 'minor' || $status == 'monitoring') {
        $emoji = '🟡';
        $color = '#fdcb6e';
    } elseif ($status == 'resolved') {
        $emoji = '🟢';
        $color = '#00b894';
    } else {
        $emoji = '🔵';
        $color = '#0984e3';
    }

    $updates = $incident['incident_updates'] ?? [];
    $latestUpdate = !empty($updates) ? $updates[0]['body'] ?? '' : '';

    // Header text based on incident type
    $headerText = $isServiceWide ? "$emoji Service-Wide Alert" : "$emoji New York Server Incident";

    $blocks = [
        [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => $headerText,
                'emoji' => true
            ]
        ],
        [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "*Incident:*\n" . ($incident['name'] ?? 'N/A')
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*Status:*\n" . strtoupper($incident['status'] ?? 'N/A')
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*Impact:*\n" . strtoupper($incident['impact'] ?? 'N/A')
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*Created:*\n" . ($incident['created_at'] ?? 'N/A')
                ]
            ]
        ]
    ];
    
    if (!empty($latestUpdate)) {
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*Latest Update:*\n$latestUpdate"
            ]
        ];
    }
    
    if (!empty($incident['url'])) {
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "<{$incident['url']}|View Incident Details>"
            ]
        ];
    }
    
    return ['blocks' => $blocks, 'color' => $color];
}

// Format component update for Slack
function formatComponent($data) {
    $component = $data['component'];
    $componentUpdate = $data['component_update'];
    $newStatus = $componentUpdate['new_status'] ?? 'UNKNOWN';
    
    $statusConfig = [
        'MAJOROUTAGE' => ['emoji' => '🔴', 'color' => '#d63031'],
        'PARTIALOUTAGE' => ['emoji' => '🟠', 'color' => '#e17055'],
        'DEGRADEDPERFORMANCE' => ['emoji' => '🟡', 'color' => '#fdcb6e'],
        'OPERATIONAL' => ['emoji' => '🟢', 'color' => '#00b894'],
        'UNDERMAINTENANCE' => ['emoji' => '🔵', 'color' => '#0984e3']
    ];
    
    $config = $statusConfig[$newStatus] ?? ['emoji' => '⚪', 'color' => '#636e72'];
    
    $blocks = [
        [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => "{$config['emoji']} New York Server Component Update",
                'emoji' => true
            ]
        ],
        [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "*Component:*\n" . ($component['name'] ?? 'N/A')
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*Status:*\n" . str_replace('_', ' ', $newStatus)
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*Updated:*\n" . ($componentUpdate['created_at'] ?? 'N/A')
                ]
            ]
        ]
    ];
    
    if (!empty($data['page']['url'])) {
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "<{$data['page']['url']}|View Status Page>"
            ]
        ];
    }
    
    return ['blocks' => $blocks, 'color' => $config['color']];
}

// Format maintenance for Slack
function formatMaintenance($data) {
    $maintenance = $data['maintenance'];
    $status = strtoupper($maintenance['status'] ?? 'SCHEDULED');

    // Determine emoji and color based on maintenance status
    $statusConfig = [
        'SCHEDULED' => ['emoji' => '🔵', 'color' => '#0984e3'],
        'INPROGRESS' => ['emoji' => '🟠', 'color' => '#e17055'],
        'IN_PROGRESS' => ['emoji' => '🟠', 'color' => '#e17055'],
        'VERIFYING' => ['emoji' => '🟡', 'color' => '#fdcb6e'],
        'COMPLETED' => ['emoji' => '🟢', 'color' => '#00b894']
    ];

    $config = $statusConfig[$status] ?? ['emoji' => '🔵', 'color' => '#0984e3'];

    $scheduledStart = $maintenance['scheduled_for'] ?? $maintenance['scheduled_start_time'] ?? 'N/A';
    $scheduledEnd = $maintenance['scheduled_until'] ?? $maintenance['scheduled_end_time'] ?? 'N/A';

    $blocks = [
        [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => "{$config['emoji']} Scheduled Maintenance Alert",
                'emoji' => true
            ]
        ],
        [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "*Maintenance:*\n" . ($maintenance['name'] ?? 'N/A')
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*Status:*\n" . $status
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*Scheduled Start:*\n" . $scheduledStart
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => "*Scheduled End:*\n" . $scheduledEnd
                ]
            ]
        ]
    ];

    // Add maintenance updates if available
    $updates = $maintenance['maintenance_updates'] ?? $maintenance['incident_updates'] ?? [];
    if (!empty($updates)) {
        $latestUpdate = $updates[0]['body'] ?? '';
        if (!empty($latestUpdate)) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Details:*\n$latestUpdate"
                ]
            ];
        }
    }

    if (!empty($maintenance['url'])) {
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "<{$maintenance['url']}|View Maintenance Details>"
            ]
        ];
    }

    return ['blocks' => $blocks, 'color' => $config['color']];
}

// Main execution
try {
    // Check IP whitelist (if configured)
    if (!isAllowedIP()) {
        logMessage("✗ Request from unauthorized IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    
    // Get the raw POST data
    $rawPayload = file_get_contents('php://input');
    
    // Parse the JSON data
    $data = json_decode($rawPayload, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("✗ Invalid JSON");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    logMessage("✓ Received webhook from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

    $shouldAlert = false;
    $isServiceWide = false;
    $message = null;

    // Check for incident
    if (isset($data['incident'])) {
        $incident = $data['incident'];
        $incidentName = $incident['name'] ?? '';

        // Check 1: Is it New York related?
        if (isNewYorkRelated($incidentName)) {
            $shouldAlert = true;
            logMessage("→ New York incident detected (name match): $incidentName");
        }

        // Check 2: Check incident updates for NY mentions
        if (!$shouldAlert) {
            foreach ($incident['incident_updates'] ?? [] as $update) {
                if (isNewYorkRelated($update['body'] ?? '')) {
                    $shouldAlert = true;
                    logMessage("→ New York incident detected (update match): $incidentName");
                    break;
                }
            }
        }

        // Check 3: Check affected components for NY
        if (!$shouldAlert && hasNewYorkComponent($data)) {
            $shouldAlert = true;
            logMessage("→ New York incident detected (component match): $incidentName");
        }

        // Check 4: Is it a service-wide incident that affects everyone?
        if (!$shouldAlert && isServiceWideIncident($data)) {
            $shouldAlert = true;
            $isServiceWide = true;
            logMessage("→ Service-wide incident detected: $incidentName");
        }

        if ($shouldAlert) {
            $message = formatIncident($data, $isServiceWide);
        }
    }

    // Check for component update
    elseif (isset($data['component'])) {
        $component = $data['component'];
        $componentName = $component['name'] ?? '';

        if (isNewYorkRelated($componentName)) {
            $shouldAlert = true;
            logMessage("→ New York component update: $componentName");
            $message = formatComponent($data);
        }
    }

    // Check for maintenance - alert if NY related
    elseif (isset($data['maintenance'])) {
        $maintenance = $data['maintenance'];
        $maintenanceName = $maintenance['name'] ?? '';

        // Check maintenance name and description for NY
        $maintenanceText = $maintenanceName . ' ' . ($maintenance['description'] ?? '');
        foreach ($maintenance['maintenance_updates'] ?? [] as $update) {
            $maintenanceText .= ' ' . ($update['body'] ?? '');
        }

        if (isNewYorkRelated($maintenanceText)) {
            $shouldAlert = true;
            logMessage("→ New York maintenance detected: $maintenanceName");
            $message = formatMaintenance($data);
        } else {
            logMessage("→ Maintenance not NY related, ignoring: $maintenanceName");
        }
    }

    // Send to Slack if needed
    if ($shouldAlert && $message) {
        sendToSlack($message['blocks'], $message['color']);
    } elseif (!$shouldAlert) {
        logMessage("→ Not relevant to our service, ignoring");
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode(['status' => 'received']);
    
} catch (Exception $e) {
    logMessage("✗ Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
