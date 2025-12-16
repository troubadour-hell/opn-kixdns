{#
 # Copyright (C) 2025 KixDNS Project
 # All rights reserved.
 #}

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#pipelines">{{ lang._('Pipelines') }}</a></li>
    <li><a data-toggle="tab" href="#selectors">{{ lang._('Selectors') }}</a></li>
    <li><a data-toggle="tab" href="#rules">{{ lang._('Rules') }}</a></li>
</ul>

<div class="tab-content content-box">
    <!-- General Settings -->
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings']) }}
            <div class="col-md-12">
                <hr />
                <button class="btn btn-primary" id="saveAct" type="button"><b>{{ lang._('Save') }}</b></button>
                <button class="btn btn-primary" id="reloadAct" type="button"><b>{{ lang._('Apply Changes') }}</b></button>
            </div>
        </div>
    </div>

    <!-- Pipelines Tab -->
    <div id="pipelines" class="tab-pane fade">
        <table id="grid-pipelines" class="table table-condensed table-hover table-striped" data-editDialog="dialogPipeline" data-editAlert="PipelineChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="id" data-type="string" data-identifier="true">{{ lang._('ID') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Selectors Tab -->
    <div id="selectors" class="tab-pane fade">
        <table id="grid-selectors" class="table table-condensed table-hover table-striped" data-editDialog="dialogSelector">
            <thead>
                <tr>
                    <th data-column-id="target_pipeline" data-type="string">{{ lang._('Target Pipeline') }}</th>
                    <th data-column-id="matchers" data-type="string">{{ lang._('Matchers') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

     <!-- Rules Tab -->
    <div id="rules" class="tab-pane fade">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                   {{ lang._('Note: Use the Pipeline ID field to link rules to pipelines defined in the Pipelines tab.') }}
                </div>
            </div>
        </div>
        <table id="grid-rules" class="table table-condensed table-hover table-striped" data-editDialog="dialogRule">
            <thead>
                <tr>
                    <th data-column-id="pipeline_id" data-type="string">{{ lang._('Pipeline ID') }}</th>
                    <th data-column-id="name" data-type="string" data-identifier="true">{{ lang._('Name') }}</th>
                    <th data-column-id="matchers" data-type="string">{{ lang._('Matchers') }}</th>
                    <th data-column-id="actions" data-type="string">{{ lang._('Actions') }}</th>
                    <th data-column-id="enabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogPipeline,'id':'dialogPipeline','label':lang._('Edit Pipeline')]) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogSelector,'id':'dialogSelector','label':lang._('Edit Selector')]) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogRule,'id':'dialogRule','label':lang._('Edit Rule')]) }}

<script>
$(document).ready(function() {
    var data_get_map = {'frm_general_settings':"/api/kixdns/settings/get"};

    // Load General Settings
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    // Save General Settings
    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/kixdns/settings/set", formid='frm_general_settings', callback_ok=function(){
            $("#saveAct").blur();
            // Reload form data after save
            mapDataToFormUI(data_get_map).done(function(data){
                formatTokenizersUI();
                $('.selectpicker').selectpicker('refresh');
            });
        });
    });

    // Reload Service
    $("#reloadAct").click(function(){
        ajaxCall(url="/api/kixdns/settings/reconfigure", sendData={}, callback=function(data,status) {
            if (data['status'] == 'ok') {
                 // reload successful
            }
        });
    });

    // Init Grids
    $("#grid-pipelines").UIBootgrid(
        { 'search':'/api/kixdns/settings/searchPipeline',
          'get':'/api/kixdns/settings/getPipeline/',
          'set':'/api/kixdns/settings/setPipeline/',
          'add':'/api/kixdns/settings/addPipeline/',
          'del':'/api/kixdns/settings/delPipeline/',
          'toggle':'/api/kixdns/settings/togglePipeline/'
        }
    );

    $("#grid-selectors").UIBootgrid(
        { 'search':'/api/kixdns/settings/searchSelector',
          'get':'/api/kixdns/settings/getSelector/',
          'set':'/api/kixdns/settings/setSelector/',
          'add':'/api/kixdns/settings/addSelector/',
          'del':'/api/kixdns/settings/delSelector/',
          'toggle':'/api/kixdns/settings/toggleSelector/'
        }
    );

    $("#grid-rules").UIBootgrid(
        { 'search':'/api/kixdns/settings/searchRule',
          'get':'/api/kixdns/settings/getRule/',
          'set':'/api/kixdns/settings/setRule/',
          'add':'/api/kixdns/settings/addRule/',
          'del':'/api/kixdns/settings/delRule/',
          'toggle':'/api/kixdns/settings/toggleRule/'
        }
    );
});
</script>

