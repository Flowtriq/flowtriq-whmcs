<?php
/**
 * Flowtriq API Client for WHMCS
 *
 * Handles all communication with the Flowtriq REST API v1.
 * Auth: Bearer <deploy_token> (64 chars)
 */

namespace Flowtriq\WHMCS;

class FlowtriqApi
{
    private string $baseUrl;
    private string $token;

    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token   = $token;
    }

    /**
     * Test the API connection by fetching workspace info.
     */
    public function testConnection(): array
    {
        return $this->request('GET', '/api/v1/workspace');
    }

    // ── Workspace Management (requires white-label) ─────────────────────────

    /**
     * Create a sub-workspace for a customer.
     */
    public function createWorkspace(string $name, string $email, string $password = '', bool $sendWelcome = true): array
    {
        $body = [
            'name'               => $name,
            'email'              => $email,
            'send_welcome_email' => $sendWelcome,
        ];
        if ($password) {
            $body['password'] = $password;
        }
        return $this->request('POST', '/api/v1/workspaces', $body);
    }

    /**
     * Get sub-workspace details including nodes.
     */
    public function getWorkspace(string $uuid): array
    {
        return $this->request('GET', '/api/v1/workspaces/' . urlencode($uuid));
    }

    /**
     * List all sub-workspaces.
     */
    public function listWorkspaces(int $limit = 50, int $offset = 0): array
    {
        return $this->request('GET', '/api/v1/workspaces?limit=' . $limit . '&offset=' . $offset);
    }

    /**
     * Deactivate a sub-workspace and all its nodes.
     */
    public function deleteWorkspace(string $uuid): array
    {
        return $this->request('DELETE', '/api/v1/workspaces/' . urlencode($uuid));
    }

    // ── Node Management ─────────────────────────────────────────────────────

    /**
     * Create a node. Uses $overrideToken to create in a sub-workspace.
     */
    public function createNode(string $name, string $ip, string $overrideToken = ''): array
    {
        return $this->request('POST', '/api/v1/nodes', [
            'name' => $name,
            'ip'   => $ip,
        ], $overrideToken ?: null);
    }

    /**
     * Get node details by UUID.
     */
    public function getNode(string $uuid, string $overrideToken = ''): array
    {
        return $this->request('GET', '/api/v1/nodes/' . urlencode($uuid), [], $overrideToken ?: null);
    }

    /**
     * Delete (deactivate) a node.
     */
    public function deleteNode(string $uuid, string $overrideToken = ''): array
    {
        return $this->request('DELETE', '/api/v1/nodes/' . urlencode($uuid), [], $overrideToken ?: null);
    }

    // ── Incidents ────────────────────────────────────────────────────────────

    /**
     * Get recent incidents for a node.
     */
    public function getIncidents(string $nodeUuid, int $limit = 5, string $overrideToken = ''): array
    {
        $query = '?node=' . urlencode($nodeUuid) . '&limit=' . $limit;
        return $this->request('GET', '/api/v1/incidents' . $query, [], $overrideToken ?: null);
    }

    // ── HTTP Client ─────────────────────────────────────────────────────────

    /**
     * Make an API request.
     *
     * @param string      $method  HTTP method
     * @param string      $endpoint  API path (e.g. /api/v1/nodes)
     * @param array       $body    Request body for POST/PATCH
     * @param string|null $tokenOverride  Use a different token (for sub-workspace operations)
     * @return array  Decoded JSON response
     */
    private function request(string $method, string $endpoint, array $body = [], ?string $tokenOverride = null): array
    {
        $url   = $this->baseUrl . $endpoint;
        $token = $tokenOverride ?? $this->token;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: FlowtriqWHMCS/1.0',
            ],
        ]);

        if (in_array($method, ['POST', 'PATCH', 'PUT']) && $body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        // Log via WHMCS module logging (masks token)
        $logRequest = [
            'method'   => $method,
            'url'      => $url,
            'body'     => $body ?: null,
        ];

        if ($curlError) {
            $result = ['ok' => false, 'error' => ['code' => 'connection_error', 'message' => 'Connection failed: ' . $curlError]];
            $this->log($method . ' ' . $endpoint, $logRequest, $rawResponse ?: $curlError, $result, $token);
            return $result;
        }

        $result = json_decode($rawResponse, true);
        if (!is_array($result)) {
            $result = ['ok' => false, 'error' => ['code' => 'invalid_response', 'message' => 'Invalid API response (HTTP ' . $httpCode . ')']];
        }

        $this->log($method . ' ' . $endpoint, $logRequest, $rawResponse, $result, $token);

        return $result;
    }

    /**
     * Log API call via WHMCS module logging.
     */
    private function log(string $action, array $request, $rawResponse, array $processed, string $token): void
    {
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'flowtriq',
                $action,
                json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                is_string($rawResponse) ? $rawResponse : json_encode($rawResponse),
                json_encode($processed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                [$token] // Mask token in logs
            );
        }
    }

    /**
     * Extract error message from API response.
     */
    public static function errorMessage(array $result): string
    {
        if (!empty($result['error']['message'])) {
            return $result['error']['message'];
        }
        if (!empty($result['error']) && is_string($result['error'])) {
            return $result['error'];
        }
        return 'Unknown API error';
    }
}
