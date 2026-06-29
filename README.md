# Flowtriq WHMCS Module

[![WHMCS 8.0+](https://img.shields.io/badge/WHMCS-8.0%2B-blue.svg)](https://www.whmcs.com/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
[![License: Proprietary](https://img.shields.io/badge/License-Proprietary-lightgrey.svg)](LICENSE)

> **[Integration Guide](https://flowtriq.com/integrations/whmcs)** | **[Documentation](https://flowtriq.com/docs)** | **[Sign Up](https://flowtriq.com/signup)**

WHMCS provisioning module for [Flowtriq](https://flowtriq.com) DDoS detection and auto-mitigation. Lets hosting providers sell DDoS protection as a WHMCS product with fully automated provisioning, suspension, and termination -- integrated with Flowtriq's white-label system so each customer gets their own isolated dashboard.

---

## Features

- **Automated provisioning** -- creates a Flowtriq workspace + monitoring node when a customer orders
- **White-label integration** -- each customer gets their own branded dashboard with login credentials
- **Billing lifecycle** -- suspend, unsuspend, and terminate tied to WHMCS billing events
- **Client area** -- protection status, recent incidents, and agent install instructions
- **Admin area** -- node status, workspace details, and quick dashboard link
- **Auto-setup** -- required custom fields are created automatically when you configure a product

## Requirements

- WHMCS 8.0+
- PHP 7.4+
- A Flowtriq account with [white-label enabled](https://flowtriq.com/white-label)

## Installation

1. Copy the module directory into your WHMCS installation:

   ```bash
   cp -r modules/servers/flowtriq /path/to/whmcs/modules/servers/
   ```

2. In WHMCS Admin, go to **Setup > Products/Services > Products/Services**
3. Create or edit a product and set the **Module** to **Flowtriq DDoS Protection**
4. Configure the module settings:
   - **API URL** -- your Flowtriq instance URL (default: `https://flowtriq.com`)
   - **Deploy Token** -- your white-label workspace deploy token (found in **Flowtriq > Settings > API**)
   - **Show Install Instructions** -- whether to display agent setup steps to customers
5. Save the product. The required custom fields will be created automatically.

## How It Works

### Provisioning Flow

When a customer orders a product using this module:

1. A **sub-workspace** is created in Flowtriq under your white-label account
2. A **user account** is created so the customer can log into their dashboard
3. A **monitoring node** is provisioned for the IP address in the Domain field
4. The customer receives their dashboard credentials and agent install instructions

### Service Lifecycle

| WHMCS Action | What Happens |
|---|---|
| **Create** | Sub-workspace + user + node created |
| **Suspend** | Node deleted (stops per-node billing), workspace kept for history |
| **Unsuspend** | New node provisioned in existing workspace |
| **Terminate** | Node deleted + workspace deactivated |

### Client Area

Customers see:
- Protection status (online/offline/under attack)
- Current traffic levels
- Recent DDoS incidents with attack type, severity, and peak PPS
- Agent installation instructions with copyable credentials
- Link to their full Flowtriq dashboard

## Product Setup Tips

- Set the **Domain** field label to "IP Address" in your product's custom fields -- this is the IP that Flowtriq will monitor
- The module uses the customer's email address to create their Flowtriq dashboard login
- Each customer gets a randomly generated password, visible in the admin custom fields

## File Structure

```
modules/servers/flowtriq/
  flowtriq.php              # Main provisioning module
  hooks.php                 # Auto-creates custom fields
  logo.png                  # Module icon
  lib/
    FlowtriqApi.php         # Flowtriq REST API client
  templates/
    admin.tpl               # Admin service detail view
    client.tpl              # Client area view
```

## API Endpoints Used

The module communicates with the Flowtriq REST API v1:

| Endpoint | Purpose |
|---|---|
| `POST /api/v1/workspaces` | Create sub-workspace for customer |
| `DELETE /api/v1/workspaces/{uuid}` | Deactivate sub-workspace |
| `POST /api/v1/nodes` | Provision monitoring node |
| `GET /api/v1/nodes/{uuid}` | Fetch node status |
| `DELETE /api/v1/nodes/{uuid}` | Remove monitoring node |
| `GET /api/v1/incidents` | Fetch recent incidents |
| `GET /api/v1/workspace` | Test API connection |

## Support

- [Flowtriq Documentation](https://flowtriq.com/docs)
- [Flowtriq Dashboard](https://flowtriq.com/dashboard)
- Email: support@flowtriq.com

## Get Started

Start your free 14-day trial at [flowtriq.com/signup](https://flowtriq.com/signup).

## License

Proprietary. Copyright Flowtriq.

---

Built by [Flowtriq](https://flowtriq.com) - Real-time DDoS detection and mitigation.
