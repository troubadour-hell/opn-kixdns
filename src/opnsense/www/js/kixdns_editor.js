/**
 * KixDNS Configuration Editor
 * Pure JavaScript/jQuery implementation (no external dependencies)
 */

(function($) {
    'use strict';

    // Field definitions for matchers
    const MATCHER_FIELDS = {
        'listener_label': ['value'],
        'client_ip': ['cidr'],
        'domain_suffix': ['value'],
        'domain_regex': ['value'],
        'qclass': ['value'],
        'edns_present': ['expect'],
        'upstream_equals': ['value'],
        'request_domain_suffix': ['value'],
        'request_domain_regex': ['value'],
        'response_type': ['value'],
        'response_rcode': ['value'],
        'response_qclass': ['value'],
        'response_edns_present': ['expect'],
        'response_upstream_ip': ['cidr'],
        'response_answer_ip': ['cidr'],
        'any': []
    };

    const DEFAULT_MATCH_OPERATOR = 'and';

    const selectorMatcherTypes = {
        'listener_label': 'Listener Label',
        'client_ip': 'Client IP',
        'domain_suffix': 'Domain Suffix',
        'domain_regex': 'Domain Regex',
        'any': 'Any',
        'qclass': 'QClass',
        'edns_present': 'EDNS Present'
    };

    const requestMatcherTypes = {
        'any': 'Any',
        'domain_suffix': 'Domain Suffix',
        'domain_regex': 'Domain Regex',
        'client_ip': 'Client IP',
        'qclass': 'QClass',
        'edns_present': 'EDNS Present'
    };

    const responseMatcherTypes = {
        'upstream_equals': 'Upstream Equals',
        'request_domain_suffix': 'Req Domain Suffix',
        'request_domain_regex': 'Req Domain Regex',
        'response_type': 'Response Type',
        'response_rcode': 'Response RCode',
        'response_qclass': 'Response QClass',
        'response_edns_present': 'Response EDNS',
        'response_upstream_ip': 'Upstream IP (CIDR)',
        'response_answer_ip': 'Answer IP (CIDR)'
    };

    // Main editor state
    let editorState = {
        config: {
            version: "1.0",
            settings: {
                min_ttl: 0,
                bind_udp: "0.0.0.0:5353",
                bind_tcp: "0.0.0.0:5353",
                default_upstream: "1.1.1.1:53",
                upstream_timeout_ms: 2000,
                response_jump_limit: 10,
                udp_pool_size: 0
            },
            pipeline_select: [],
            pipelines: []
        },
        rawJson: '',
        manualJsonEdit: false,
        loading: false,
        saving: false,
        currentTab: 'editor',
        activePipelineIndex: -1
    };

    // Helper functions
    function toCleanJsonString(cfgObj) {
        return JSON.stringify(cfgObj, (key, value) => {
            if (key && key.startsWith('_')) return undefined;
            return value;
        }, 2);
    }

    function hasField(type, field) {
        return (MATCHER_FIELDS[type] || []).includes(field);
    }

    function ensureUniquePipelineId(base, self) {
        const clean = base && base.trim() ? base.trim() : 'pipeline';
        let candidate = clean;
        let counter = 1;
        while (editorState.config.pipelines.some(p => p !== self && p.id === candidate)) {
            candidate = `${clean}_${counter++}`;
        }
        return candidate;
    }

    function generatePipelineId() {
        return ensureUniquePipelineId('pipeline');
    }

    function generateRuleName(pipeline) {
        const base = 'rule';
        let idx = 1;
        let candidate = `${base}_${idx}`;
        const existing = new Set(pipeline.rules.map(r => r.name));
        while (existing.has(candidate)) {
            idx += 1;
            candidate = `${base}_${idx}`;
        }
        return candidate;
    }

    function replacePipelineRefs(oldId, newId) {
        if (!oldId || oldId === newId) return;
        editorState.config.pipeline_select.forEach(sel => {
            if (sel.pipeline === oldId) sel.pipeline = newId;
        });
        editorState.config.pipelines.forEach(pipe => {
            pipe.rules.forEach(rule => {
                rule.actions.forEach(a => {
                    if (a.type === 'jump_to_pipeline' && a.pipeline === oldId) {
                        a.pipeline = newId;
                    }
                });
                (rule.response_actions_on_match || []).forEach(a => {
                    if (a.type === 'jump_to_pipeline' && a.pipeline === oldId) {
                        a.pipeline = newId;
                    }
                });
                (rule.response_actions_on_miss || []).forEach(a => {
                    if (a.type === 'jump_to_pipeline' && a.pipeline === oldId) {
                        a.pipeline = newId;
                    }
                });
            });
        });
    }

    function normalizeMatcherOps(items) {
        (items || []).forEach(m => {
            if (!m.operator) m.operator = DEFAULT_MATCH_OPERATOR;
        });
    }

    function normalizeRule(rule) {
        rule.matchers = rule.matchers || [];
        rule.matcher_operator = rule.matcher_operator || DEFAULT_MATCH_OPERATOR;
        normalizeMatcherOps(rule.matchers);
        rule.actions = rule.actions || [];
        rule.response_matchers = rule.response_matchers || [];
        rule.response_matcher_operator = rule.response_matcher_operator || DEFAULT_MATCH_OPERATOR;
        normalizeMatcherOps(rule.response_matchers);
        rule.response_actions_on_match = rule.response_actions_on_match || [];
        rule.response_actions_on_miss = rule.response_actions_on_miss || [];
    }

    function normalizePipelineSelect(sel) {
        sel.matchers = sel.matchers || [];
        sel.matcher_operator = sel.matcher_operator || DEFAULT_MATCH_OPERATOR;
        normalizeMatcherOps(sel.matchers);
    }

    // Load config from backend
    function loadConfig() {
        editorState.loading = true;
        $.ajax({
            url: '/api/kixdns/settings/getConfigJson',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.config_json) {
                    try {
                        const parsed = JSON.parse(data.config_json);
                        (parsed.pipelines || []).forEach(p => {
                            p._oldId = p.id;
                            p._syncedId = p.id;
                            (p.rules || []).forEach(r => normalizeRule(r));
                        });
                        (parsed.pipeline_select || []).forEach(sel => normalizePipelineSelect(sel));
                        editorState.config = parsed;
                        editorState.rawJson = toCleanJsonString(editorState.config);
                        renderEditor();
                    } catch (e) {
                        alert('配置解析失败: ' + e.message);
                    }
                } else {
                    editorState.rawJson = toCleanJsonString(editorState.config);
                    renderEditor();
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load config:', error);
                alert('加载配置失败: ' + error);
            },
            complete: function() {
                editorState.loading = false;
            }
        });
    }

    // Save config to backend
    function saveConfig() {
        editorState.saving = true;
        const cleanConfig = JSON.parse(toCleanJsonString(editorState.config));
        $.ajax({
            url: '/api/kixdns/settings/saveConfigJson',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ config_json: JSON.stringify(cleanConfig) }),
            dataType: 'json',
            success: function(data) {
                if (data.result === 'saved') {
                    alert('配置已保存');
                } else {
                    alert('保存失败: ' + (data.message || '未知错误'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to save config:', error);
                alert('保存配置失败: ' + error);
            },
            complete: function() {
                editorState.saving = false;
            }
        });
    }

    // Apply changes
    function applyChanges() {
        saveConfig();
        $.ajax({
            url: '/api/kixdns/settings/reconfigure',
            method: 'POST',
            dataType: 'json',
            success: function(data) {
                if (data.status === 'ok') {
                    alert('配置已应用');
                } else {
                    alert('应用配置失败');
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to apply changes:', error);
                alert('应用配置失败: ' + error);
            }
        });
    }

    // Load JSON from textarea
    function loadJson() {
        try {
            const parsed = JSON.parse(editorState.rawJson);
            (parsed.pipelines || []).forEach(p => {
                p._oldId = p.id;
                p._syncedId = p.id;
                (p.rules || []).forEach(r => normalizeRule(r));
            });
            (parsed.pipeline_select || []).forEach(sel => normalizePipelineSelect(sel));
            editorState.config = parsed;
            editorState.manualJsonEdit = false;
            renderEditor();
        } catch (e) {
            alert('JSON 解析失败: ' + e.message);
        }
    }

    // Download JSON
    function downloadJson() {
        const blob = new Blob([editorState.rawJson], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'pipeline.json';
        a.click();
        URL.revokeObjectURL(url);
    }

    // Render matcher list
    function renderMatcherList(container, matchers, types, withOperator) {
        container.empty();
        matchers.forEach((m, idx) => {
            const row = $('<div>').addClass('input-group input-group-sm').css('margin-bottom', '0.25rem');
            if (withOperator) {
                const opSelect = $('<select>').addClass('form-control').css('max-width', '120px')
                    .append($('<option>').attr('value', 'and').text('AND'))
                    .append($('<option>').attr('value', 'or').text('OR'))
                    .append($('<option>').attr('value', 'and_not').text('AND NOT'))
                    .append($('<option>').attr('value', 'or_not').text('OR NOT'))
                    .append($('<option>').attr('value', 'not').text('NOT'))
                    .val(m.operator || DEFAULT_MATCH_OPERATOR)
                    .on('change', function() { m.operator = $(this).val(); updateJsonPreview(); });
                row.append(opSelect);
            }
            const typeSelect = $('<select>').addClass('form-control').css('max-width', '140px')
                .on('change', function() {
                    const type = $(this).val();
                    m.type = type;
                    // Reset fields
                    delete m.value;
                    delete m.cidr;
                    delete m.expect;
                    if (hasField(type, 'value')) m.value = '';
                    if (hasField(type, 'cidr')) m.cidr = '';
                    if (hasField(type, 'expect')) m.expect = true;
                    renderMatcherList(container, matchers, types, withOperator);
                    updateJsonPreview();
                });
            Object.keys(types).forEach(type => {
                typeSelect.append($('<option>').attr('value', type).text(types[type]));
            });
            typeSelect.val(m.type);
            row.append(typeSelect);

            if (hasField(m.type, 'value')) {
                const input = $('<input>').attr('type', 'text').addClass('form-control')
                    .attr('placeholder', 'Value').val(m.value || '')
                    .on('input', function() { m.value = $(this).val(); updateJsonPreview(); });
                row.append(input);
            }
            if (hasField(m.type, 'cidr')) {
                const input = $('<input>').attr('type', 'text').addClass('form-control')
                    .attr('placeholder', 'CIDR').val(m.cidr || '')
                    .on('input', function() { m.cidr = $(this).val(); updateJsonPreview(); });
                row.append(input);
            }
            if (hasField(m.type, 'expect')) {
                const checkDiv = $('<div>').addClass('input-group-addon');
                const checkbox = $('<input>').attr('type', 'checkbox')
                    .prop('checked', m.expect !== false)
                    .on('change', function() { m.expect = $(this).prop('checked'); updateJsonPreview(); });
                checkDiv.append(checkbox).append(' Expect');
                row.append(checkDiv);
            }

            const delBtn = $('<button>').addClass('btn btn-danger').text('×')
                .on('click', function() {
                    matchers.splice(idx, 1);
                    renderMatcherList(container, matchers, types, withOperator);
                    updateJsonPreview();
                });
            row.append(delBtn);
            container.append(row);
        });
        const addBtn = $('<button>').addClass('btn btn-link btn-sm').css('padding', 0)
            .text('+ 添加条件')
            .on('click', function() {
                const firstType = Object.keys(types)[0];
                matchers.push({ type: firstType, value: '', operator: DEFAULT_MATCH_OPERATOR });
                renderMatcherList(container, matchers, types, withOperator);
                updateJsonPreview();
            });
        container.append(addBtn);
    }

    // Render action list
    function renderActionList(container, actions, pipelines, currentPipelineId) {
        container.empty();
        actions.forEach((a, idx) => {
            const row = $('<div>').addClass('input-group input-group-sm').css('margin-bottom', '0.25rem');
            const typeSelect = $('<select>').addClass('form-control').css('max-width', '140px')
                .append($('<option>').attr('value', 'log').text('Log'))
                .append($('<option>').attr('value', 'static_response').text('Static Response'))
                .append($('<option>').attr('value', 'static_ip_response').text('Static IP'))
                .append($('<option>').attr('value', 'jump_to_pipeline').text('Jump to Pipeline'))
                .append($('<option>').attr('value', 'allow').text('Allow (Pass)'))
                .append($('<option>').attr('value', 'deny').text('Deny (Drop)'))
                .append($('<option>').attr('value', 'forward').text('Forward'))
                .append($('<option>').attr('value', 'continue').text('Continue'))
                .val(a.type)
                .on('change', function() {
                    const type = $(this).val();
                    // Reset fields
                    Object.keys(a).forEach(key => { if (key !== 'type') delete a[key]; });
                    if (type === 'log') a.level = 'info';
                    if (type === 'static_response') a.rcode = 'NXDOMAIN';
                    if (type === 'static_ip_response') a.ip = '127.0.0.1';
                    if (type === 'jump_to_pipeline') a.pipeline = '';
                    if (type === 'forward') { a.upstream = ''; a.transport = null; }
                    a.type = type;
                    renderActionList(container, actions, pipelines, currentPipelineId);
                    updateJsonPreview();
                });
            row.append(typeSelect);

            if (a.type === 'log') {
                const levelSelect = $('<select>').addClass('form-control')
                    .append($('<option>').attr('value', 'trace').text('Trace'))
                    .append($('<option>').attr('value', 'debug').text('Debug'))
                    .append($('<option>').attr('value', 'info').text('Info'))
                    .append($('<option>').attr('value', 'warn').text('Warn'))
                    .append($('<option>').attr('value', 'error').text('Error'))
                    .val(a.level || 'info')
                    .on('change', function() { a.level = $(this).val(); updateJsonPreview(); });
                row.append(levelSelect);
            }
            if (a.type === 'static_response') {
                const rcodeSelect = $('<select>').addClass('form-control')
                    .append($('<option>').attr('value', 'NOERROR').text('NOERROR'))
                    .append($('<option>').attr('value', 'NXDOMAIN').text('NXDOMAIN'))
                    .append($('<option>').attr('value', 'SERVFAIL').text('SERVFAIL'))
                    .append($('<option>').attr('value', 'REFUSED').text('REFUSED'))
                    .val(a.rcode || 'NXDOMAIN')
                    .on('change', function() { a.rcode = $(this).val(); updateJsonPreview(); });
                row.append(rcodeSelect);
            }
            if (a.type === 'static_ip_response') {
                const ipInput = $('<input>').attr('type', 'text').addClass('form-control')
                    .attr('placeholder', 'IP Address').val(a.ip || '')
                    .on('input', function() { a.ip = $(this).val(); updateJsonPreview(); });
                row.append(ipInput);
            }
            if (a.type === 'jump_to_pipeline') {
                const pipelineSelect = $('<select>').addClass('form-control')
                    .append($('<option>').attr('value', '').text('选择 Pipeline').prop('disabled', true));
                pipelines.forEach(p => {
                    if (p.id !== currentPipelineId) {
                        pipelineSelect.append($('<option>').attr('value', p.id).text(p.id));
                    }
                });
                pipelineSelect.val(a.pipeline || '')
                    .on('change', function() { a.pipeline = $(this).val(); updateJsonPreview(); });
                row.append(pipelineSelect);
            }
            if (a.type === 'forward') {
                const upstreamInput = $('<input>').attr('type', 'text').addClass('form-control')
                    .attr('placeholder', 'Upstream (Optional)').val(a.upstream || '')
                    .on('input', function() { a.upstream = $(this).val(); updateJsonPreview(); });
                row.append(upstreamInput);
                const transportSelect = $('<select>').addClass('form-control').css('max-width', '80px')
                    .append($('<option>').attr('value', '').text('Auto'))
                    .append($('<option>').attr('value', 'udp').text('UDP'))
                    .append($('<option>').attr('value', 'tcp').text('TCP'))
                    .val(a.transport || '')
                    .on('change', function() { a.transport = $(this).val() || null; updateJsonPreview(); });
                row.append(transportSelect);
            }

            const delBtn = $('<button>').addClass('btn btn-danger').text('×')
                .on('click', function() {
                    actions.splice(idx, 1);
                    renderActionList(container, actions, pipelines, currentPipelineId);
                    updateJsonPreview();
                });
            row.append(delBtn);
            container.append(row);
        });
        const addBtn = $('<button>').addClass('btn btn-link btn-sm').css('padding', 0)
            .text('+ 添加动作')
            .on('click', function() {
                actions.push({ type: 'log', level: 'info' });
                renderActionList(container, actions, pipelines, currentPipelineId);
                updateJsonPreview();
            });
        container.append(addBtn);
    }

    // Update JSON preview
    function updateJsonPreview() {
        if (!editorState.manualJsonEdit) {
            editorState.rawJson = toCleanJsonString(editorState.config);
            $('#kixdns-json-preview').val(editorState.rawJson);
        }
    }

    // Render full editor
    function renderEditor() {
        const app = $('#kixdns-app');
        if (app.length === 0) return;

        // Render settings
        $('#kixdns-settings-bind-udp').val(editorState.config.settings.bind_udp);
        $('#kixdns-settings-bind-tcp').val(editorState.config.settings.bind_tcp);
        $('#kixdns-settings-default-upstream').val(editorState.config.settings.default_upstream);
        $('#kixdns-settings-min-ttl').val(editorState.config.settings.min_ttl);
        $('#kixdns-settings-upstream-timeout').val(editorState.config.settings.upstream_timeout_ms);
        $('#kixdns-settings-response-jump-limit').val(editorState.config.settings.response_jump_limit);
        $('#kixdns-settings-udp-pool-size').val(editorState.config.settings.udp_pool_size);

        // Render pipeline select
        const selectContainer = $('#kixdns-pipeline-select-container');
        selectContainer.empty();
        editorState.config.pipeline_select.forEach((sel, idx) => {
            const item = $('<div>').addClass('panel panel-default').css('margin-bottom', '0.5rem');
            const header = $('<div>').addClass('panel-heading')
                .append($('<span>').addClass('badge').text('#' + (idx + 1)).css('margin-right', '0.5rem'))
                .append($('<select>').addClass('form-control input-sm').css('display', 'inline-block').css('width', 'auto')
                    .append($('<option>').attr('value', '').text('选择 Pipeline').prop('disabled', true))
                    .append(editorState.config.pipelines.map(p => 
                        $('<option>').attr('value', p.id).text(p.id)
                    ))
                    .val(sel.pipeline || '')
                    .on('change', function() { sel.pipeline = $(this).val(); updateJsonPreview(); }))
                .append($('<button>').addClass('btn btn-danger btn-sm pull-right').text('删除')
                    .on('click', function() {
                        editorState.config.pipeline_select.splice(idx, 1);
                        renderEditor();
                        updateJsonPreview();
                    }));
            const body = $('<div>').addClass('panel-body');
            const matcherContainer = $('<div>').css('padding-left', '1rem').css('border-left', '2px solid #ddd');
            renderMatcherList(matcherContainer, sel.matchers, selectorMatcherTypes, false);
            body.append($('<div>').addClass('text-muted small').text('匹配条件 (Matchers):'));
            body.append(matcherContainer);
            item.append(header).append(body);
            selectContainer.append(item);
        });

        // Render pipelines
        const pipelinesContainer = $('#kixdns-pipelines-container');
        pipelinesContainer.empty();
        editorState.config.pipelines.forEach((pipe, pIdx) => {
            const panel = $('<div>').addClass('panel panel-default');
            const heading = $('<div>').addClass('panel-heading')
                .append($('<h4>').addClass('panel-title')
                    .append($('<a>').attr('data-toggle', 'collapse').attr('data-target', '#pipeline-' + pIdx)
                        .text(pipe.id).css('font-weight', 'bold')
                        .append($('<span>').addClass('badge').text(pipe.rules.length + ' 规则').css('margin-left', '0.5rem'))));
            const collapse = $('<div>').attr('id', 'pipeline-' + pIdx).addClass('panel-collapse collapse')
                .toggle(pIdx === editorState.activePipelineIndex);
            const body = $('<div>').addClass('panel-body');
            
            const idGroup = $('<div>').addClass('form-group');
            idGroup.append($('<label>').text('Pipeline ID'));
            const idInputGroup = $('<div>').addClass('input-group');
            idInputGroup.append($('<input>').attr('type', 'text').addClass('form-control')
                .val(pipe.id)
                .data('old-id', pipe.id)
                .on('focus', function() { $(this).data('old-id', $(this).val()); })
                .on('blur', function() {
                    const oldId = $(this).data('old-id');
                    let newId = $(this).val().trim();
                    if (!newId) {
                        alert('Pipeline ID 不能为空，将还原为原值');
                        $(this).val(oldId);
                        return;
                    }
                    const uniqueId = ensureUniquePipelineId(newId, pipe);
                    if (uniqueId !== newId) {
                        alert('Pipeline ID 已存在，已调整为 ' + uniqueId);
                    }
                    pipe.id = uniqueId;
                    if (oldId !== uniqueId) {
                        replacePipelineRefs(oldId, uniqueId);
                        alert('已将 Pipeline ID 从 ' + oldId + ' 更新为 ' + uniqueId + '，所有引用已同步');
                    }
                    renderEditor();
                    updateJsonPreview();
                }));
            idInputGroup.append($('<span>').addClass('input-group-btn')
                .append($('<button>').addClass('btn btn-danger').text('删除 Pipeline')
                    .on('click', function() {
                        if (confirm('确定要删除 Pipeline "' + pipe.id + '" 吗？')) {
                            editorState.config.pipelines.splice(pIdx, 1);
                            renderEditor();
                            updateJsonPreview();
                        }
                    })));
            idGroup.append(idInputGroup);
            body.append(idGroup);

            body.append($('<h6>').css('font-weight', 'bold').text('规则列表 (Rules)'));
            pipe.rules.forEach((rule, rIdx) => {
                const rulePanel = $('<div>').addClass('panel panel-info').css('margin-bottom', '0.5rem');
                const ruleHeader = $('<div>').addClass('panel-heading')
                    .append($('<input>').attr('type', 'text').addClass('form-control input-sm')
                        .css('display', 'inline-block').css('width', '50%')
                        .attr('placeholder', '规则名称').val(rule.name || '')
                        .on('input', function() { rule.name = $(this).val(); updateJsonPreview(); }))
                    .append($('<button>').addClass('btn btn-danger btn-sm pull-right').text('×')
                        .on('click', function() {
                            pipe.rules.splice(rIdx, 1);
                            renderEditor();
                            updateJsonPreview();
                        }));
                const ruleBody = $('<div>').addClass('panel-body');
                
                ruleBody.append($('<span>').addClass('label label-info').text('请求匹配 (Matchers)'));
                const reqMatcherContainer = $('<div>').css('margin-top', '0.5rem');
                renderMatcherList(reqMatcherContainer, rule.matchers, requestMatcherTypes, true);
                ruleBody.append(reqMatcherContainer);

                ruleBody.append($('<span>').addClass('label label-warning').css('margin-top', '0.5rem').css('display', 'block').text('执行动作 (Actions)'));
                const actionContainer = $('<div>').css('margin-top', '0.5rem');
                renderActionList(actionContainer, rule.actions, editorState.config.pipelines, pipe.id);
                ruleBody.append(actionContainer);

                const hasForward = rule.actions.some(a => a.type === 'forward');
                if (hasForward) {
                    ruleBody.append($('<span>').addClass('label label-success').css('margin-top', '0.5rem').css('display', 'block').text('响应匹配 (Response Matchers)'));
                    const respMatcherContainer = $('<div>').css('margin-top', '0.5rem');
                    renderMatcherList(respMatcherContainer, rule.response_matchers || [], responseMatcherTypes, true);
                    ruleBody.append(respMatcherContainer);
                    ruleBody.append($('<div>').addClass('help-block small').text('仅在匹配 `forward` 动作时生效'));

                    ruleBody.append($('<span>').addClass('label label-primary').css('margin-top', '0.5rem').css('display', 'block').text('响应匹配成功动作'));
                    const respMatchActionContainer = $('<div>').css('margin-top', '0.5rem');
                    renderActionList(respMatchActionContainer, rule.response_actions_on_match || [], editorState.config.pipelines, pipe.id);
                    ruleBody.append(respMatchActionContainer);

                    ruleBody.append($('<span>').addClass('label label-danger').css('margin-top', '0.5rem').css('display', 'block').text('响应匹配失败动作'));
                    const respMissActionContainer = $('<div>').css('margin-top', '0.5rem');
                    renderActionList(respMissActionContainer, rule.response_actions_on_miss || [], editorState.config.pipelines, pipe.id);
                    ruleBody.append(respMissActionContainer);
                }

                rulePanel.append(ruleHeader).append(ruleBody);
                body.append(rulePanel);
            });
            body.append($('<button>').addClass('btn btn-default btn-sm').css('width', '100%')
                .text('+ 添加规则')
                .on('click', function() {
                    const name = generateRuleName(pipe);
                    const rule = {
                        name: name,
                        matchers: [],
                        matcher_operator: DEFAULT_MATCH_OPERATOR,
                        actions: [],
                        response_matchers: [],
                        response_matcher_operator: DEFAULT_MATCH_OPERATOR,
                        response_actions_on_match: [],
                        response_actions_on_miss: []
                    };
                    normalizeRule(rule);
                    pipe.rules.push(rule);
                    renderEditor();
                    updateJsonPreview();
                }));

            collapse.append(body);
            panel.append(heading).append(collapse);
            pipelinesContainer.append(panel);
        });

        // Update JSON preview
        updateJsonPreview();
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only initialize if editor tab exists
        if ($('#editor').length === 0) return;

        // Bind settings inputs
        $('#kixdns-settings-bind-udp').on('input', function() {
            editorState.config.settings.bind_udp = $(this).val();
            updateJsonPreview();
        });
        $('#kixdns-settings-bind-tcp').on('input', function() {
            editorState.config.settings.bind_tcp = $(this).val();
            updateJsonPreview();
        });
        $('#kixdns-settings-default-upstream').on('input', function() {
            editorState.config.settings.default_upstream = $(this).val();
            updateJsonPreview();
        });
        $('#kixdns-settings-min-ttl').on('input', function() {
            editorState.config.settings.min_ttl = parseInt($(this).val()) || 0;
            updateJsonPreview();
        });
        $('#kixdns-settings-upstream-timeout').on('input', function() {
            editorState.config.settings.upstream_timeout_ms = parseInt($(this).val()) || 2000;
            updateJsonPreview();
        });
        $('#kixdns-settings-response-jump-limit').on('input', function() {
            editorState.config.settings.response_jump_limit = parseInt($(this).val()) || 10;
            updateJsonPreview();
        });
        $('#kixdns-settings-udp-pool-size').on('input', function() {
            editorState.config.settings.udp_pool_size = parseInt($(this).val()) || 0;
            updateJsonPreview();
        });

        // Bind JSON textarea
        $('#kixdns-json-preview').on('input', function() {
            editorState.rawJson = $(this).val();
            editorState.manualJsonEdit = true;
        });

        // Bind buttons
        $('#kixdns-load-json').on('click', loadJson);
        $('#kixdns-download-json').on('click', downloadJson);
        $('#kixdns-save-config').on('click', saveConfig);
        $('#kixdns-apply-changes').on('click', applyChanges);
        $('#kixdns-add-pipeline-select').on('click', function() {
            editorState.config.pipeline_select.push({
                pipeline: '',
                matchers: [],
                matcher_operator: DEFAULT_MATCH_OPERATOR
            });
            renderEditor();
            updateJsonPreview();
        });
        $('#kixdns-add-pipeline').on('click', function() {
            const newId = generatePipelineId();
            editorState.config.pipelines.push({
                id: newId,
                rules: [],
                _oldId: newId,
                _syncedId: newId
            });
            renderEditor();
            updateJsonPreview();
        });

        // Load config on tab switch
        $('a[data-toggle="tab"][href="#editor"]').on('shown.bs.tab', function() {
            if (editorState.config.pipelines.length === 0 && !editorState.loading) {
                loadConfig();
            }
        });

        // File upload
        $('#kixdns-file-upload').on('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                editorState.rawJson = e.target.result;
                $('#kixdns-json-preview').val(editorState.rawJson);
            };
            reader.readAsText(file);
        });
    });

})(jQuery);
