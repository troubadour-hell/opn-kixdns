{#
 # Copyright (C) 2025 KixDNS Project
 # All rights reserved.
 #}
<style>
    .editor-pane { height: calc(100vh - 200px); overflow-y: auto; border-right: 1px solid #ddd; padding: 15px; }
    .preview-pane { height: calc(100vh - 200px); overflow-y: auto; background-color: #2d2d2d; color: #f8f9fa; padding: 15px; }
    .matcher-item, .action-item { background-color: #fff; border: 1px solid #ddd; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 0.25rem; }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#editor">{{ lang._('Pipeline Editor') }}</a></li>
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

    <!-- Pipeline Editor Tab -->
    <div id="editor" class="tab-pane fade">
        <div id="kixdns-app" class="container-fluid">
            <div class="row">
                <!-- Editor Pane -->
                <div class="col-md-7 editor-pane">
                    <h2>KixDNS 配置编辑器</h2>
                    
                    <!-- Toolbar -->
                    <div class="btn-toolbar" role="toolbar" style="margin-bottom: 15px;">
                        <div class="btn-group">
                            <button class="btn btn-primary" id="kixdns-load-json">从右侧加载 JSON</button>
                            <button class="btn btn-success" id="kixdns-download-json">下载 JSON</button>
                            <label class="btn btn-default">
                                导入文件 <input type="file" id="kixdns-file-upload" style="display: none;">
                            </label>
                            <button class="btn btn-warning" id="kixdns-save-config">保存配置</button>
                            <button class="btn btn-danger" id="kixdns-apply-changes">应用更改</button>
                        </div>
                    </div>

                    <!-- Global Settings -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>全局设置 (Settings)</strong></div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>UDP 监听地址</label>
                                        <input type="text" class="form-control" id="kixdns-settings-bind-udp">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>TCP 监听地址</label>
                                        <input type="text" class="form-control" id="kixdns-settings-bind-tcp">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>默认上游</label>
                                        <input type="text" class="form-control" id="kixdns-settings-default-upstream">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>最小 TTL</label>
                                        <input type="number" class="form-control" id="kixdns-settings-min-ttl">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>上游超时 (ms)</label>
                                        <input type="number" class="form-control" id="kixdns-settings-upstream-timeout">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>响应跳转上限</label>
                                        <input type="number" class="form-control" id="kixdns-settings-response-jump-limit">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>UDP 连接池大小</label>
                                        <input type="number" class="form-control" id="kixdns-settings-udp-pool-size" placeholder="0=禁用">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pipeline Selectors -->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>分流规则 (Pipeline Select)</strong>
                            <button class="btn btn-xs btn-primary pull-right" id="kixdns-add-pipeline-select">+ 添加规则</button>
                        </div>
                        <div class="panel-body" id="kixdns-pipeline-select-container">
                            <!-- Dynamically populated -->
                        </div>
                    </div>

                    <!-- Pipelines -->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>处理流程 (Pipelines)</strong>
                            <button class="btn btn-xs btn-primary pull-right" id="kixdns-add-pipeline">+ 添加 Pipeline</button>
                        </div>
                        <div class="panel-body" id="kixdns-pipelines-container">
                            <!-- Dynamically populated -->
                        </div>
                    </div>
                </div>

                <!-- Preview Pane -->
                <div class="col-md-5 preview-pane">
                    <h4>JSON 预览 / 编辑</h4>
                    <textarea class="form-control" id="kixdns-json-preview" 
                        style="height: calc(100vh - 250px); font-size: 12px; font-family: monospace; background-color: #2d2d2d; color: #f8f9fa; border: 1px solid #555;"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

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
});
</script>

<script src="/ui/js/kixdns_editor.js"></script>
