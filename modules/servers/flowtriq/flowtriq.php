<?php
/**
 * Flowtriq WHMCS Provisioning Module
 *
 * Automates DDoS protection provisioning for hosting providers using
 * Flowtriq's white-label sub-workspace system.
 *
 * Each WHMCS customer gets their own isolated Flowtriq workspace with
 * dashboard access, billed at the white-label rate ($7.99/node/month).
 *
 * Lifecycle:
 *   CreateAccount   -> Creates sub-workspace + user + node
 *   SuspendAccount  -> Deletes node (stops billing, keeps workspace)
 *   UnsuspendAccount -> Re-provisions node in existing workspace
 *   TerminateAccount -> Deletes node + deactivates workspace
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/FlowtriqApi.php';

use Flowtriq\WHMCS\FlowtriqApi;
use WHMCS\Database\Capsule;

// ── Module Meta ─────────────────────────────────────────────────────────────

function flowtriq_MetaData(): array
{
    return [
        'DisplayName' => 'Flowtriq DDoS Protection',
        'APIVersion'  => '1.1',
        'RequiresServer' => false,
    ];
}

// ── Config Options (per-product settings) ───────────────────────────────────

function flowtriq_ConfigOptions(): array
{
    return [
        'API URL' => [
            'Type'        => 'text',
            'Size'        => 60,
            'Default'     => 'https://flowtriq.com',
            'Description' => 'Flowtriq API base URL (no trailing slash)',
        ],
        'Deploy Token' => [
            'Type'        => 'password',
            'Size'        => 64,
            'Description' => 'Your white-label deploy token from Flowtriq > Settings > API',
        ],
        'Show Install Instructions' => [
            'Type'        => 'yesno',
            'Default'     => 'yes',
            'Description' => 'Show agent installation instructions in client area',
        ],
    ];
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build API client from module params.
 */
function flowtriq_api(array $params): FlowtriqApi
{
    return new FlowtriqApi(
        $params['configoption1'] ?: 'https://flowtriq.com',
        $params['configoption2']
    );
}

/**
 * Get a custom field value for a service.
 */
function flowtriq_get_field(int $serviceId, int $productId, string $fieldName): string
{
    $fieldId = Capsule::table('tblcustomfields')
        ->where('relid', $productId)
        ->where('fieldname', $fieldName)
        ->value('id');

    if (!$fieldId) return '';

    return (string)Capsule::table('tblcustomfieldsvalues')
        ->where('fieldid', $fieldId)
        ->where('relid', $serviceId)
        ->value('value') ?: '';
}

/**
 * Set a custom field value for a service.
 */
function flowtriq_set_field(int $serviceId, int $productId, string $fieldName, string $value): void
{
    $fieldId = Capsule::table('tblcustomfields')
        ->where('relid', $productId)
        ->where('fieldname', $fieldName)
        ->value('id');

    if (!$fieldId) return;

    Capsule::table('tblcustomfieldsvalues')->updateOrInsert(
        ['fieldid' => $fieldId, 'relid' => $serviceId],
        ['value' => $value]
    );
}

/**
 * Build a safe node name from client details.
 */
function flowtriq_node_name(array $params): string
{
    $name = $params['domain'];
    // Sanitize to match Flowtriq's name regex: [a-zA-Z0-9._\-\s()]{1,100}
    $name = preg_replace('/[^a-zA-Z0-9._\-\s()]/', '', $name);
    return substr($name, 0, 100) ?: 'node-' . $params['serviceid'];
}

/**
 * Build a workspace name from client details.
 */
function flowtriq_workspace_name(array $params): string
{
    $client = $params['clientsdetails'];
    $company = $client['companyname'] ?? '';
    $name = $company ?: ($client['firstname'] . ' ' . $client['lastname']);
    $name = trim($name) . ' (#' . $params['serviceid'] . ')';
    // Sanitize
    $name = preg_replace('/[^\w\s.\-#()]/', '', $name);
    return substr($name, 0, 100) ?: 'Workspace ' . $params['serviceid'];
}

// ── Provisioning Functions ──────────────────────────────────────────────────

function flowtriq_CreateAccount(array $params): string
{
    $api       = flowtriq_api($params);
    $serviceId = $params['serviceid'];
    $productId = $params['pid'];
    $domain    = trim($params['domain']);

    // Validate IP
    if (!$domain || !filter_var($domain, FILTER_VALIDATE_IP)) {
        return 'A valid IP address is required in the Domain field.';
    }

    $clientEmail = $params['clientsdetails']['email'] ?? '';
    if (!$clientEmail) {
        return 'Client email address is required for workspace creation.';
    }

    // Step 1: Create sub-workspace
    $wsName   = flowtriq_workspace_name($params);
    $password = bin2hex(random_bytes(8)); // 16-char random password

    $wsResult = $api->createWorkspace($wsName, $clientEmail, $password, true);

    if (empty($wsResult['ok'])) {
        $err = FlowtriqApi::errorMessage($wsResult);
        // If user already exists, the workspace may already be provisioned
        if (strpos($err, 'already exists') !== false) {
            return 'A Flowtriq account with this email already exists. Use a unique email or contact support.';
        }
        return 'Failed to create workspace: ' . $err;
    }

    $wsUuid       = $wsResult['workspace']['uuid'] ?? '';
    $wsDeployToken = $wsResult['workspace']['deploy_token'] ?? '';
    $userPassword  = $wsResult['user']['password'] ?? $password;

    // Store workspace details
    flowtriq_set_field($serviceId, $productId, 'flowtriq_workspace_uuid', $wsUuid);
    flowtriq_set_field($serviceId, $productId, 'flowtriq_workspace_deploy_token', $wsDeployToken);
    flowtriq_set_field($serviceId, $productId, 'flowtriq_workspace_password', $userPassword);

    // Step 2: Create node in the sub-workspace
    $nodeName = flowtriq_node_name($params);

    $nodeResult = $api->createNode($nodeName, $domain, $wsDeployToken);

    if (empty($nodeResult['ok'])) {
        $err = FlowtriqApi::errorMessage($nodeResult);
        // Retry once with suffix if duplicate name
        if (strpos($err, 'already exists') !== false) {
            $nodeName .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
            $nodeResult = $api->createNode($nodeName, $domain, $wsDeployToken);
        }
        if (empty($nodeResult['ok'])) {
            return 'Workspace created but node provisioning failed: ' . FlowtriqApi::errorMessage($nodeResult);
        }
    }

    $nodeUuid   = $nodeResult['node']['uuid'] ?? '';
    $nodeApiKey = $nodeResult['node']['api_key'] ?? '';

    flowtriq_set_field($serviceId, $productId, 'flowtriq_node_uuid', $nodeUuid);
    flowtriq_set_field($serviceId, $productId, 'flowtriq_node_api_key', $nodeApiKey);

    return 'success';
}

function flowtriq_SuspendAccount(array $params): string
{
    $api       = flowtriq_api($params);
    $serviceId = $params['serviceid'];
    $productId = $params['pid'];

    $nodeUuid   = flowtriq_get_field($serviceId, $productId, 'flowtriq_node_uuid');
    $wsToken    = flowtriq_get_field($serviceId, $productId, 'flowtriq_workspace_deploy_token');

    if (!$nodeUuid) {
        return 'success'; // Nothing to suspend
    }

    $result = $api->deleteNode($nodeUuid, $wsToken);

    // 404 = already deleted = success
    if (!empty($result['ok']) || (!empty($result['error']['code']) && $result['error']['code'] === 'not_found')) {
        // Clear node fields but keep workspace alive
        flowtriq_set_field($serviceId, $productId, 'flowtriq_node_uuid', '');
        flowtriq_set_field($serviceId, $productId, 'flowtriq_node_api_key', '');
        return 'success';
    }

    return 'Failed to suspend: ' . FlowtriqApi::errorMessage($result);
}

function flowtriq_UnsuspendAccount(array $params): string
{
    $api       = flowtriq_api($params);
    $serviceId = $params['serviceid'];
    $productId = $params['pid'];
    $domain    = trim($params['domain']);

    if (!$domain || !filter_var($domain, FILTER_VALIDATE_IP)) {
        return 'A valid IP address is required in the Domain field.';
    }

    $wsToken = flowtriq_get_field($serviceId, $productId, 'flowtriq_workspace_deploy_token');
    if (!$wsToken) {
        return 'Workspace deploy token not found. Please re-create this service.';
    }

    // Re-provision node in the existing workspace
    $nodeName = flowtriq_node_name($params);
    $result   = $api->createNode($nodeName, $domain, $wsToken);

    if (empty($result['ok'])) {
        $err = FlowtriqApi::errorMessage($result);
        if (strpos($err, 'already exists') !== false) {
            $nodeName .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
            $result = $api->createNode($nodeName, $domain, $wsToken);
        }
        if (empty($result['ok'])) {
            return 'Failed to re-provision node: ' . FlowtriqApi::errorMessage($result);
        }
    }

    $nodeUuid   = $result['node']['uuid'] ?? '';
    $nodeApiKey = $result['node']['api_key'] ?? '';

    flowtriq_set_field($serviceId, $productId, 'flowtriq_node_uuid', $nodeUuid);
    flowtriq_set_field($serviceId, $productId, 'flowtriq_node_api_key', $nodeApiKey);

    return 'success';
}

function flowtriq_TerminateAccount(array $params): string
{
    $api       = flowtriq_api($params);
    $serviceId = $params['serviceid'];
    $productId = $params['pid'];

    $nodeUuid = flowtriq_get_field($serviceId, $productId, 'flowtriq_node_uuid');
    $wsToken  = flowtriq_get_field($serviceId, $productId, 'flowtriq_workspace_deploy_token');
    $wsUuid   = flowtriq_get_field($serviceId, $productId, 'flowtriq_workspace_uuid');

    // Delete node first (if exists)
    if ($nodeUuid && $wsToken) {
        $api->deleteNode($nodeUuid, $wsToken);
    }

    // Deactivate workspace (uses parent token)
    if ($wsUuid) {
        $result = $api->deleteWorkspace($wsUuid);
        if (empty($result['ok']) && (!isset($result['error']['code']) || $result['error']['code'] !== 'not_found')) {
            return 'Node removed but workspace deactivation failed: ' . FlowtriqApi::errorMessage($result);
        }
    }

    // Clear all custom fields
    foreach (['flowtriq_node_uuid', 'flowtriq_node_api_key', 'flowtriq_workspace_uuid', 'flowtriq_workspace_deploy_token', 'flowtriq_workspace_password'] as $field) {
        flowtriq_set_field($serviceId, $productId, $field, '');
    }

    return 'success';
}

// ── Admin Area ──────────────────────────────────────────────────────────────

function flowtriq_AdminServicesTabFields(array $params): array
{
    $serviceId = $params['serviceid'];
    $productId = $params['pid'];
    $apiUrl    = $params['configoption1'] ?: 'https://flowtriq.com';

    $nodeUuid = flowtriq_get_field($serviceId, $productId, 'flowtriq_node_uuid');
    $wsUuid   = flowtriq_get_field($serviceId, $productId, 'flowtriq_workspace_uuid');
    $wsToken  = flowtriq_get_field($serviceId, $productId, 'flowtriq_workspace_deploy_token');

    if (!$wsUuid) {
        return ['Status' => '<span style="color:#999">Not Provisioned</span>'];
    }

    $fields = [
        'Workspace UUID' => $wsUuid,
    ];

    if (!$nodeUuid) {
        $fields['Node Status'] = '<span style="color:#f39c12">Suspended (no active node)</span>';
        $fields['Dashboard'] = '<a href="' . htmlspecialchars($apiUrl) . '/dashboard/" target="_blank">Open Dashboard</a>';
        return $fields;
    }

    // Try to fetch live node data
    $api = flowtriq_api($params);
    $nodeData = $api->getNode($nodeUuid, $wsToken);

    if (!empty($nodeData['ok']) && !empty($nodeData['node'])) {
        $node = $nodeData['node'];
        $statusColors = [
            'online'   => '#27ae60',
            'offline'  => '#95a5a6',
            'attack'   => '#e74c3c',
            'elevated' => '#f39c12',
        ];
        $status = $node['status'] ?? 'unknown';
        $color  = $statusColors[$status] ?? '#95a5a6';

        $fields['Node UUID']    = $nodeUuid;
        $fields['Status']       = '<span style="color:' . $color . ';font-weight:bold">' . strtoupper($status) . '</span>';
        $fields['IP Address']   = $node['ip'] ?? $params['domain'];
        $fields['Last Seen']    = $node['last_seen'] ?? 'Never';
        $fields['Current PPS']  = number_format($node['last_pps'] ?? 0);

        $apiKey = flowtriq_get_field($serviceId, $productId, 'flowtriq_node_api_key');
        if ($apiKey) {
            $fields['Agent API Key'] = substr($apiKey, 0, 12) . '...';
        }
    } else {
        $fields['Node UUID'] = $nodeUuid;
        $fields['Status']    = '<span style="color:#95a5a6">Unable to fetch status</span>';
    }

    $fields['Dashboard'] = '<a href="' . htmlspecialchars($apiUrl) . '/dashboard/" target="_blank">Open Dashboard</a>';

    return $fields;
}

// ── Client Area ─────────────────────────────────────────────────────────────

function flowtriq_ClientArea(array $params): array
{
    $serviceId = $params['serviceid'];
    $productId = $params['pid'];
    $apiUrl    = $params['configoption1'] ?: 'https://flowtriq.com';

    $nodeUuid    = flowtriq_get_field($serviceId, $productId, 'flowtriq_node_uuid');
    $nodeApiKey  = flowtriq_get_field($serviceId, $productId, 'flowtriq_node_api_key');
    $wsUuid      = flowtriq_get_field($serviceId, $productId, 'flowtriq_workspace_uuid');
    $wsToken     = flowtriq_get_field($serviceId, $productId, 'flowtriq_workspace_deploy_token');
    $wsPassword  = flowtriq_get_field($serviceId, $productId, 'flowtriq_workspace_password');

    $templateVars = [
        'apiUrl'        => $apiUrl,
        'nodeUuid'      => $nodeUuid,
        'nodeApiKey'    => $nodeApiKey,
        'wsUuid'        => $wsUuid,
        'clientEmail'   => $params['clientsdetails']['email'] ?? '',
        'wsPassword'    => $wsPassword,
        'showInstall'   => ($params['configoption3'] ?? '') === 'on',
        'domain'        => $params['domain'],
        'node'          => null,
        'incidents'     => [],
        'error'         => '',
        'suspended'     => false,
    ];

    if (!$wsUuid) {
        $templateVars['error'] = 'Service not yet provisioned.';
        return [
            'templateFile' => 'templates/client',
            'vars'         => $templateVars,
        ];
    }

    if (!$nodeUuid) {
        $templateVars['suspended'] = true;
        return [
            'templateFile' => 'templates/client',
            'vars'         => $templateVars,
        ];
    }

    // Fetch live data
    $api = flowtriq_api($params);

    $nodeData = $api->getNode($nodeUuid, $wsToken);
    if (!empty($nodeData['ok']) && !empty($nodeData['node'])) {
        $templateVars['node'] = $nodeData['node'];
    }

    $incidentData = $api->getIncidents($nodeUuid, 5, $wsToken);
    if (!empty($incidentData['ok']) && !empty($incidentData['incidents'])) {
        $templateVars['incidents'] = $incidentData['incidents'];
    }

    return [
        'templateFile' => 'templates/client',
        'vars'         => $templateVars,
    ];
}

// ── Test Connection ─────────────────────────────────────────────────────────

function flowtriq_TestConnection(array $params): array
{
    $api    = flowtriq_api($params);
    $result = $api->testConnection();

    if (!empty($result['ok'])) {
        return ['success' => true, 'error' => ''];
    }

    return ['success' => false, 'error' => FlowtriqApi::errorMessage($result)];
}
