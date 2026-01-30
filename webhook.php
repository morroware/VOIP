<?php
/**
 * Webhook Monitor for New York Server
 * Reports New York server incidents to Slack
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
    $keywords = ['new york', 'newyork', 'ny server', 'nyc', 'new-york', 'ny-1'];
    
    foreach ($keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
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
function formatIncident($data) {
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
    
    $blocks = [
        [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => "$emoji New York Server Incident",
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
    $message = null;
    
    // Check for incident
    if (isset($data['incident'])) {
        $incident = $data['incident'];
        $incidentName = $incident['name'] ?? '';
        
        if (isNewYorkRelated($incidentName)) {
            $shouldAlert = true;
        } else {
            // Check incident updates
            foreach ($incident['incident_updates'] ?? [] as $update) {
                if (isNewYorkRelated($update['body'] ?? '')) {
                    $shouldAlert = true;
                    break;
                }
            }
        }
        
        if ($shouldAlert) {
            logMessage("→ New York incident detected: $incidentName");
            $message = formatIncident($data);
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
    
    // Check for maintenance (optional - logs but doesn't alert)
    elseif (isset($data['maintenance'])) {
        $maintenance = $data['maintenance'];
        $maintenanceName = $maintenance['name'] ?? '';
        
        if (isNewYorkRelated($maintenanceName)) {
            logMessage("→ New York maintenance: $maintenanceName (not alerting)");
        }
    }
    
    // Send to Slack if needed
    if ($shouldAlert && $message) {
        sendToSlack($message['blocks'], $message['color']);
    } elseif (!$shouldAlert) {
        logMessage("→ Not New York related, ignoring");
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
