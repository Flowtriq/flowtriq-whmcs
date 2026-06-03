<div class="flowtriq-client-area">

    {* ── Error / Not Provisioned ─────────────────────────────────────────── *}
    {if $error}
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> {$error}
        </div>
    {elseif $suspended}
        <div class="alert alert-warning">
            <i class="fas fa-pause-circle"></i>
            <strong>Protection Suspended</strong> &mdash; Your DDoS protection is currently suspended.
            Contact your hosting provider to reactivate.
        </div>

        {if $wsUuid && $clientEmail}
        <div class="panel panel-default mt-3">
            <div class="panel-heading"><h3 class="panel-title">Dashboard Access</h3></div>
            <div class="panel-body">
                <p>You can still access your dashboard to view incident history:</p>
                <p>
                    <a href="{$apiUrl}/login" class="btn btn-default" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt"></i> Open Dashboard
                    </a>
                </p>
                <p class="text-muted small">Login: {$clientEmail}</p>
            </div>
        </div>
        {/if}

    {else}
        {* ── Status Banner ───────────────────────────────────────────────── *}
        {if $node}
            {if $node.status == 'online'}
                <div class="alert alert-success">
                    <i class="fas fa-shield-alt"></i>
                    <strong>Protected</strong> &mdash; Your server is being monitored for DDoS attacks.
                </div>
            {elseif $node.status == 'attack'}
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Under Attack</strong> &mdash; A DDoS attack has been detected. Mitigation is active.
                </div>
            {elseif $node.status == 'elevated'}
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Elevated Traffic</strong> &mdash; Unusual traffic levels detected. Monitoring closely.
                </div>
            {else}
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Agent Offline</strong> &mdash; The monitoring agent is not reporting. Please check your server.
                </div>
            {/if}
        {/if}

        {* ── Node Details ────────────────────────────────────────────────── *}
        {if $node}
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Protection Details</h3></div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-6">
                        <dl class="dl-horizontal">
                            <dt>IP Address</dt>
                            <dd>{$domain}</dd>
                            <dt>Status</dt>
                            <dd>
                                {if $node.status == 'online'}
                                    <span class="label label-success">ONLINE</span>
                                {elseif $node.status == 'attack'}
                                    <span class="label label-danger">UNDER ATTACK</span>
                                {elseif $node.status == 'elevated'}
                                    <span class="label label-warning">ELEVATED</span>
                                {else}
                                    <span class="label label-default">OFFLINE</span>
                                {/if}
                            </dd>
                            <dt>Current Traffic</dt>
                            <dd>{$node.last_pps|default:0|number_format:0} pps</dd>
                            <dt>Last Seen</dt>
                            <dd>{$node.last_seen|default:'Never'}</dd>
                        </dl>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="{$apiUrl}/login" class="btn btn-primary" target="_blank" rel="noopener">
                            <i class="fas fa-chart-line"></i> Open Dashboard
                        </a>
                        <p class="text-muted small mt-2">Login: {$clientEmail}</p>
                    </div>
                </div>
            </div>
        </div>
        {/if}

        {* ── Recent Incidents ────────────────────────────────────────────── *}
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Recent Incidents</h3></div>
            {if $incidents && count($incidents) > 0}
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Attack Type</th>
                            <th>Severity</th>
                            <th>Peak PPS</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $incidents as $incident}
                        <tr>
                            <td>{$incident.started_at|default:'-'}</td>
                            <td>{$incident.family|default:$incident.attack_family|default:'Unknown'}</td>
                            <td>
                                {if $incident.severity == 'critical'}
                                    <span class="label label-danger">Critical</span>
                                {elseif $incident.severity == 'high'}
                                    <span class="label label-warning">High</span>
                                {elseif $incident.severity == 'medium'}
                                    <span class="label label-info">Medium</span>
                                {else}
                                    <span class="label label-default">{$incident.severity|default:'Low'}</span>
                                {/if}
                            </td>
                            <td>{$incident.peak_pps|default:0|number_format:0}</td>
                            <td>
                                {if $incident.status == 'active'}
                                    <span class="label label-danger">Active</span>
                                {else}
                                    <span class="label label-success">Resolved</span>
                                {/if}
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <div class="panel-body text-muted">
                <i class="fas fa-check-circle text-success"></i>
                No incidents detected. Your server is clean.
            </div>
            {/if}
        </div>

        {* ── Agent Setup Instructions ────────────────────────────────────── *}
        {if $showInstall && $nodeApiKey && $nodeUuid}
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Agent Installation</h3></div>
            <div class="panel-body">
                <p>Install the Flowtriq agent on your server to enable DDoS monitoring:</p>

                <h5>1. Install the agent</h5>
                <pre><code>pip install ftagent[full]</code></pre>

                <h5>2. Run the setup wizard</h5>
                <pre><code>sudo ftagent --setup</code></pre>

                <p>When prompted, enter the following credentials:</p>

                <div class="well well-sm">
                    <div class="row">
                        <div class="col-sm-6">
                            <strong>Node UUID:</strong>
                            <div class="input-group mt-1">
                                <input type="text" class="form-control input-sm" value="{$nodeUuid}" id="ft-node-uuid" readonly>
                                <span class="input-group-btn">
                                    <button class="btn btn-default btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('ft-node-uuid').value)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <strong>API Key:</strong>
                            <div class="input-group mt-1">
                                <input type="password" class="form-control input-sm" value="{$nodeApiKey}" id="ft-api-key" readonly>
                                <span class="input-group-btn">
                                    <button class="btn btn-default btn-sm" type="button" onclick="var f=document.getElementById('ft-api-key');f.type=f.type==='password'?'text':'password'">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-default btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('ft-api-key').value)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <h5>3. Start the service</h5>
                <pre><code>sudo ftagent --install-service
sudo systemctl enable --now ftagent</code></pre>
            </div>
        </div>
        {/if}

    {/if}

</div>

<style>
.flowtriq-client-area .dl-horizontal dt { width: 130px; }
.flowtriq-client-area .dl-horizontal dd { margin-left: 150px; }
.flowtriq-client-area .mt-1 { margin-top: 5px; }
.flowtriq-client-area .mt-2 { margin-top: 10px; }
.flowtriq-client-area .mt-3 { margin-top: 15px; }
.flowtriq-client-area .mb-0 { margin-bottom: 0; }
.flowtriq-client-area pre { background: #1a1a2e; color: #e0e0e0; padding: 12px; border-radius: 4px; }
.flowtriq-client-area pre code { color: #e0e0e0; }
</style>
