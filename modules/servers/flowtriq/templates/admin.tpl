<div class="flowtriq-admin-area">

    {if !$wsUuid}
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> This service has not been provisioned yet.
        </div>
    {else}
        <table class="table table-bordered table-condensed">
            <tr>
                <td class="text-right" style="width:180px"><strong>Workspace UUID</strong></td>
                <td><code>{$wsUuid}</code></td>
            </tr>
            {if $nodeUuid}
            <tr>
                <td class="text-right"><strong>Node UUID</strong></td>
                <td><code>{$nodeUuid}</code></td>
            </tr>
            <tr>
                <td class="text-right"><strong>Status</strong></td>
                <td>
                    {if $nodeStatus == 'online'}
                        <span class="label label-success">ONLINE</span>
                    {elseif $nodeStatus == 'attack'}
                        <span class="label label-danger">UNDER ATTACK</span>
                    {elseif $nodeStatus == 'elevated'}
                        <span class="label label-warning">ELEVATED</span>
                    {else}
                        <span class="label label-default">OFFLINE</span>
                    {/if}
                </td>
            </tr>
            <tr>
                <td class="text-right"><strong>IP Address</strong></td>
                <td>{$nodeIp}</td>
            </tr>
            <tr>
                <td class="text-right"><strong>Current PPS</strong></td>
                <td>{$nodePps|number_format:0}</td>
            </tr>
            <tr>
                <td class="text-right"><strong>Last Seen</strong></td>
                <td>{$nodeLastSeen|default:'Never'}</td>
            </tr>
            <tr>
                <td class="text-right"><strong>Agent API Key</strong></td>
                <td><code>{$nodeApiKeyMasked}</code></td>
            </tr>
            {else}
            <tr>
                <td class="text-right"><strong>Node Status</strong></td>
                <td><span class="label label-warning">Suspended (no active node)</span></td>
            </tr>
            {/if}
            <tr>
                <td class="text-right"><strong>Dashboard</strong></td>
                <td>
                    <a href="{$dashboardUrl}" target="_blank" rel="noopener" class="btn btn-xs btn-primary">
                        <i class="fas fa-external-link-alt"></i> Open Dashboard
                    </a>
                </td>
            </tr>
        </table>
    {/if}

</div>
