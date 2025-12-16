{#
 # Copyright (C) 2025 KixDNS Project
 # All rights reserved.
 #}
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<style>
    .editor-pane { height: calc(100vh - 200px); overflow-y: auto; border-right: 1px solid #dee2e6; }
    .preview-pane { height: calc(100vh - 200px); overflow-y: auto; background-color: #212529; color: #f8f9fa; }
    .matcher-item, .action-item { background-color: #fff; border: 1px solid #dee2e6; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 0.25rem; }
    pre { margin: 0; }
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
                <div class="col-md-7 editor-pane p-4">
                    <h2 class="mb-4">KixDNS 配置编辑器</h2>
                    
                    <!-- Toolbar -->
                    <div class="mb-3 d-flex gap-2">
                        <button class="btn btn-primary" @click="loadJson" :disabled="loading">从右侧加载 JSON</button>
                        <button class="btn btn-success" @click="downloadJson">下载 JSON</button>
                        <label class="btn btn-outline-secondary">
                            导入文件 <input type="file" hidden @change="handleFileUpload">
                        </label>
                        <button class="btn btn-warning" @click="saveConfig" :disabled="saving">{{ saving ? '保存中...' : '保存配置' }}</button>
                        <button class="btn btn-danger" @click="applyChanges" :disabled="saving">{{ saving ? '应用中...' : '应用更改' }}</button>
                    </div>

                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: currentTab === 'editor' }" href="#" @click.prevent="currentTab = 'editor'">配置编辑</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: currentTab === 'flowchart' }" href="#" @click.prevent="renderFlowchart">流程图</a>
                        </li>
                    </ul>

                    <div v-show="currentTab === 'flowchart'">
                        <div class="card">
                            <div class="card-body text-center overflow-auto">
                                <div ref="mermaidRef"></div>
                            </div>
                        </div>
                    </div>

                    <div v-show="currentTab === 'editor'">
                        <!-- Global Settings -->
                        <div class="card">
                            <div class="card-header bg-light fw-bold">全局设置 (Settings)</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">UDP 监听地址</label>
                                        <input type="text" class="form-control" v-model="config.settings.bind_udp">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">TCP 监听地址</label>
                                        <input type="text" class="form-control" v-model="config.settings.bind_tcp">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">默认上游</label>
                                        <input type="text" class="form-control" v-model="config.settings.default_upstream">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">最小 TTL</label>
                                        <input type="number" class="form-control" v-model.number="config.settings.min_ttl">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">上游超时 (ms)</label>
                                        <input type="number" class="form-control" v-model.number="config.settings.upstream_timeout_ms">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">响应跳转上限</label>
                                        <input type="number" class="form-control" v-model.number="config.settings.response_jump_limit">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">UDP 连接池大小</label>
                                        <input type="number" class="form-control" v-model.number="config.settings.udp_pool_size" placeholder="0=禁用">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pipeline Selectors -->
                        <div class="card">
                            <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                                <span>分流规则 (Pipeline Select)</span>
                                <button class="btn btn-sm btn-outline-primary" @click="addPipelineSelect">+ 添加规则</button>
                            </div>
                            <div class="card-body">
                                <div v-for="(sel, idx) in config.pipeline_select" :key="idx" class="matcher-item">
                                    <div class="d-flex gap-2 mb-2 align-items-center">
                                        <span class="badge bg-secondary">#{{ idx + 1 }}</span>
                                        <select class="form-select form-select-sm" style="width: auto;" v-model="sel.pipeline">
                                            <option disabled value="">选择 Pipeline</option>
                                            <option v-for="p in config.pipelines" :value="p.id">{{ p.id }}</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-danger ms-auto" @click="removePipelineSelect(idx)">删除</button>
                                    </div>
                                    <div class="ps-3 border-start">
                                        <div class="text-muted small mb-1">匹配条件 (Matchers):</div>
                                        <matcher-list :matchers="sel.matchers" :types="selectorMatcherTypes"></matcher-list>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pipelines -->
                        <div class="card">
                            <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                                <span>处理流程 (Pipelines)</span>
                                <button class="btn btn-sm btn-outline-primary" @click="addPipeline">+ 添加 Pipeline</button>
                            </div>
                            <div class="card-body p-0">
                                <div class="accordion" id="pipelineAccordion">
                                    <div v-for="(pipe, pIdx) in config.pipelines" :key="pIdx" class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button" :class="{ collapsed: activePipelineIndex !== pIdx }" type="button" @click="togglePipeline(pIdx)">
                                                <span class="fw-bold me-2">{{ pipe.id }}</span>
                                                <span class="badge bg-secondary">{{ pipe.rules.length }} 规则</span>
                                            </button>
                                        </h2>
                                        <div class="accordion-collapse collapse" :class="{ show: activePipelineIndex === pIdx }">
                                            <div class="accordion-body bg-light">
                                                <div class="mb-3">
                                                    <label class="form-label">Pipeline ID</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" v-model="pipe.id" @focus="cacheOldPipelineId(pipe)" @blur="onPipelineIdBlur(pipe)">
                                                        <button class="btn btn-outline-danger" @click="removePipeline(pIdx)">删除 Pipeline</button>
                                                    </div>
                                                </div>
                                                
                                                <h6 class="fw-bold">规则列表 (Rules)</h6>
                                                <div v-for="(rule, rIdx) in pipe.rules" :key="rIdx" class="card mb-3 shadow-sm">
                                                    <div class="card-header d-flex justify-content-between align-items-center py-1">
                                                        <input type="text" class="form-control form-control-sm w-50" v-model="rule.name" placeholder="规则名称">
                                                        <button class="btn btn-sm btn-close" @click="removeRule(pipe, rIdx)"></button>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="mb-2">
                                                            <span class="badge bg-info text-dark">请求匹配 (Matchers)</span>
                                                            <matcher-list :matchers="rule.matchers" :types="requestMatcherTypes"></matcher-list>
                                                        </div>
                                                        <div class="mb-2">
                                                            <span class="badge bg-warning text-dark">执行动作 (Actions)</span>
                                                            <action-list :actions="rule.actions" :pipelines="config.pipelines" :current-pipeline-id="pipe.id"></action-list>
                                                        </div>
                                                        <div v-if="ruleHasForwardAction(rule)">
                                                            <span class="badge bg-success">响应匹配 (Response Matchers)</span>
                                                            <matcher-list :matchers="rule.response_matchers" :types="responseMatcherTypes"></matcher-list>
                                                            <div class="form-text small text-muted">仅在匹配 `forward` 动作时生效</div>
                                                            <div class="mt-2">
                                                                <span class="badge bg-primary">响应匹配成功动作</span>
                                                                <action-list :actions="rule.response_actions_on_match" :pipelines="config.pipelines" :current-pipeline-id="pipe.id"></action-list>
                                                            </div>
                                                            <div class="mt-2">
                                                                <span class="badge bg-danger">响应匹配失败动作</span>
                                                                <action-list :actions="rule.response_actions_on_miss" :pipelines="config.pipelines" :current-pipeline-id="pipe.id"></action-list>
                                                            </div>
                                                            <div class="form-text small text-muted">收到上游响应后执行，成功/失败分支均可使用所有动作。</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button class="btn btn-sm btn-outline-secondary w-100" @click="addRule(pipe)">+ 添加规则</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <!-- End of Editor Tab -->
                </div>

                <!-- Preview Pane -->
                <div class="col-md-5 preview-pane p-4">
                    <h4 class="mb-3">JSON 预览 / 编辑</h4>
                    <textarea class="form-control font-monospace bg-dark text-light border-secondary" 
                        style="height: calc(100vh - 250px); font-size: 12px;" 
                        v-model="rawJson" 
                        @input="manualJsonEdit = true"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Component Templates -->
<script type="text/x-template" id="matcher-list-template">
    <div>
        <div v-for="(m, idx) in matchers" :key="idx" class="input-group input-group-sm mb-1">
            <select v-if="withOperator" class="form-select" style="max-width: 120px;" v-model="m.operator">
                <option value="and">AND</option>
                <option value="or">OR</option>
                <option value="and_not">AND NOT</option>
                <option value="or_not">OR NOT</option>
                <option value="not">NOT</option>
            </select>
            <select class="form-select" style="max-width: 140px;" v-model="m.type" @change="resetMatcherFields(m)">
                <option v-for="(label, type) in types" :value="type">{{ label }}</option>
            </select>
            
            <!-- Dynamic Inputs based on type -->
            <input v-if="hasField(m.type, 'value')" type="text" class="form-control" v-model="m.value" placeholder="Value">
            <input v-if="hasField(m.type, 'cidr')" type="text" class="form-control" v-model="m.cidr" placeholder="CIDR 或逗号分隔列表">
            <div v-if="hasField(m.type, 'expect')" class="input-group-text bg-white">
                <input type="checkbox" class="form-check-input mt-0" v-model="m.expect"> &nbsp;Expect
            </div>

            <button class="btn btn-outline-danger" @click="matchers.splice(idx, 1)">×</button>
        </div>
        <button class="btn btn-sm btn-link text-decoration-none p-0" @click="addMatcher">+ 添加条件</button>
    </div>
</script>

<script type="text/x-template" id="action-list-template">
    <div>
        <div v-for="(a, idx) in actions" :key="idx" class="input-group input-group-sm mb-1">
            <select class="form-select" style="max-width: 140px;" v-model="a.type" @change="resetActionFields(a)">
                <option value="log">Log</option>
                <option value="static_response">Static Response</option>
                <option value="static_ip_response">Static IP</option>
                <option value="jump_to_pipeline">Jump to Pipeline</option>
                <option value="allow">Allow (Pass)</option>
                <option value="deny">Deny (Drop)</option>
                <option value="forward">Forward</option>
                <option value="continue">Continue</option>
            </select>

            <!-- Log -->
            <select v-if="a.type === 'log'" class="form-select" v-model="a.level">
                <option value="trace">Trace</option>
                <option value="debug">Debug</option>
                <option value="info">Info</option>
                <option value="warn">Warn</option>
                <option value="error">Error</option>
            </select>

            <!-- Static Response -->
            <select v-if="a.type === 'static_response'" class="form-select" v-model="a.rcode">
                <option value="NOERROR">NOERROR</option>
                <option value="NXDOMAIN">NXDOMAIN</option>
                <option value="SERVFAIL">SERVFAIL</option>
                <option value="REFUSED">REFUSED</option>
            </select>

            <!-- Static IP -->
            <input v-if="a.type === 'static_ip_response'" type="text" class="form-control" v-model="a.ip" placeholder="IP Address">

            <!-- Jump -->
            <select v-if="a.type === 'jump_to_pipeline'" class="form-select" v-model="a.pipeline">
                <option disabled value="">选择 Pipeline</option>
                <option v-for="p in pipelineOptions" :key="p" :value="p" :disabled="p === currentPipelineId">{{ p }}</option>
            </select>

            <!-- Forward -->
            <template v-if="a.type === 'forward'">
                <input type="text" class="form-control" v-model="a.upstream" placeholder="Upstream (Optional)">
                <select class="form-select" style="max-width: 80px;" v-model="a.transport">
                    <option :value="null">Auto</option>
                    <option value="udp">UDP</option>
                    <option value="tcp">TCP</option>
                </select>
            </template>

            <button class="btn btn-outline-danger" @click="actions.splice(idx, 1)">×</button>
        </div>
        <button class="btn btn-sm btn-link text-decoration-none p-0" @click="addAction">+ 添加动作</button>
    </div>
</script>

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
