<?php
/**
 * Flowtriq WHMCS Hooks
 *
 * Auto-creates the required custom fields when a product is saved
 * with the Flowtriq provisioning module.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * When a product is saved with the flowtriq module, ensure
 * all required custom fields exist.
 */
add_hook('ProductEdit', 1, function ($vars) {
    $productId = $vars['pid'] ?? 0;
    if (!$productId) return;

    // Check if this product uses the flowtriq module
    try {
        $module = Capsule::table('tblproducts')
            ->where('id', $productId)
            ->value('servertype');

        if ($module !== 'flowtriq') return;
    } catch (\Exception $e) {
        return;
    }

    $fields = [
        'flowtriq_node_uuid' => 'Flowtriq Node UUID',
        'flowtriq_node_api_key' => 'Flowtriq Node API Key',
        'flowtriq_workspace_uuid' => 'Flowtriq Workspace UUID',
        'flowtriq_workspace_deploy_token' => 'Flowtriq Workspace Token',
        'flowtriq_workspace_password' => 'Flowtriq Dashboard Password',
    ];

    foreach ($fields as $fieldName => $friendlyName) {
        try {
            $exists = Capsule::table('tblcustomfields')
                ->where('relid', $productId)
                ->where('fieldname', $fieldName)
                ->exists();

            if (!$exists) {
                Capsule::table('tblcustomfields')->insert([
                    'type'      => 'product',
                    'relid'     => $productId,
                    'fieldname' => $fieldName,
                    'fieldtype' => 'text',
                    'adminonly' => 'on',
                    'showorder' => '',
                    'showinvoice' => '',
                    'description' => $friendlyName . ' (auto-managed by Flowtriq module)',
                    'sortorder' => 0,
                ]);
            }
        } catch (\Exception $e) {
            logActivity('Flowtriq: Failed to create custom field ' . $fieldName . ': ' . $e->getMessage());
        }
    }
});
