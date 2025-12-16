/**
 * KixDNS Configuration Editor
 * Based on tools/config_editor.html, adapted for OPNsense plugin
 */

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

// MatcherList Component
const MatcherList = {
    template: '#matcher-list-template',
    props: {
        matchers: Array,
        types: Object,
        withOperator: {
            type: Boolean,
            default: true,
        }
    },
    setup(props) {
        const addMatcher = () => {
            const firstType = Object.keys(props.types)[0];
            props.matchers.push({ type: firstType, value: '', operator: DEFAULT_MATCH_OPERATOR });
        };
        const hasField = (type, field) => {
            return (MATCHER_FIELDS[type] || []).includes(field);
        };
        const resetMatcherFields = (m) => {
            const type = m.type;
            for (const key in m) { if (key !== 'type' && key !== 'operator') delete m[key]; }
            if (hasField(type, 'value')) m.value = '';
            if (hasField(type, 'cidr')) m.cidr = '';
            if (hasField(type, 'expect')) m.expect = true;
            if (!m.operator) m.operator = DEFAULT_MATCH_OPERATOR;
        };
        return { addMatcher, hasField, resetMatcherFields };
    }
};

// ActionList Component
const ActionList = {
    template: '#action-list-template',
    props: ['actions', 'pipelines', 'currentPipelineId'],
    setup(props) {
        const { computed } = Vue;
        const pipelineOptions = computed(() => (props.pipelines || []).map(p => p.id));
        const addAction = () => {
            props.actions.push({ type: 'log', level: 'info' });
        };
        const resetActionFields = (a) => {
            const type = a.type;
            for (const key in a) { if (key !== 'type') delete a[key]; }
            if (type === 'log') a.level = 'info';
            if (type === 'static_response') a.rcode = 'NXDOMAIN';
            if (type === 'static_ip_response') a.ip = '127.0.0.1';
            if (type === 'jump_to_pipeline') a.pipeline = '';
            if (type === 'forward') { a.upstream = ''; a.transport = null; }
        };
        return { addAction, resetActionFields, pipelineOptions };
    }
};

// Main Vue App
function initKixDNSEditor() {
    const { createApp, ref, watch, nextTick, onMounted } = Vue;

    createApp({
        components: { MatcherList, ActionList },
        setup() {
            const hash32 = (str) => {
                let h = 0x811c9dc5;
                for (let i = 0; i < str.length; i++) {
                    h ^= str.charCodeAt(i);
                    h = Math.imul(h, 0x01000193);
                }
                return (h >>> 0).toString(36);
            };

            const makeSafeId = (str) => {
                const base = (str ?? 'node').toString();
                let slug = base.replace(/[^a-zA-Z0-9_]/g, '_');
                slug = slug.replace(/_+/g, '_').replace(/^_+|_+$/g, '');
                if (!slug) slug = 'p';
                if (!/^[a-zA-Z]/.test(slug)) slug = 'p_' + slug;
                if (slug.length > 24) slug = slug.slice(0, 24);
                return `${slug}_${hash32(base)}`;
            };

            const config = ref({
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
            });

            const rawJson = ref('');
            const manualJsonEdit = ref(false);
            const loading = ref(false);
            const saving = ref(false);

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

            const toCleanJsonString = (cfgObj) => JSON.stringify(cfgObj, (key, value) => {
                if (key && key.startsWith('_')) return undefined;
                return value;
            }, 2);

            // Load config from backend
            const loadConfig = async () => {
                loading.value = true;
                try {
                    const response = await fetch('/api/kixdns/settings/getConfigJson');
                    const data = await response.json();
                    if (data.config_json) {
                        const parsed = JSON.parse(data.config_json);
                        (parsed.pipelines || []).forEach(p => {
                            p._oldId = p.id;
                            p._syncedId = p.id;
                            (p.rules || []).forEach(r => normalizeRule(r));
                        });
                        (parsed.pipeline_select || []).forEach(sel => normalizePipelineSelect(sel));
                        config.value = parsed;
                        rawJson.value = toCleanJsonString(config.value);
                    } else {
                        // Initialize with defaults
                        rawJson.value = toCleanJsonString(config.value);
                    }
                } catch (e) {
                    console.error('Failed to load config:', e);
                    alert('加载配置失败: ' + e.message);
                } finally {
                    loading.value = false;
                }
            };

            // Save config to backend
            const saveConfig = async () => {
                saving.value = true;
                try {
                    const cleanConfig = JSON.parse(toCleanJsonString(config.value));
                    const response = await fetch('/api/kixdns/settings/saveConfigJson', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ config_json: JSON.stringify(cleanConfig) })
                    });
                    const data = await response.json();
                    if (data.result === 'saved') {
                        alert('配置已保存');
                    } else {
                        alert('保存失败: ' + (data.message || '未知错误'));
                    }
                } catch (e) {
                    console.error('Failed to save config:', e);
                    alert('保存配置失败: ' + e.message);
                } finally {
                    saving.value = false;
                }
            };

            // Apply changes (save + reconfigure)
            const applyChanges = async () => {
                await saveConfig();
                try {
                    const response = await fetch('/api/kixdns/settings/reconfigure', {
                        method: 'POST'
                    });
                    const data = await response.json();
                    if (data.status === 'ok') {
                        alert('配置已应用');
                    } else {
                        alert('应用配置失败');
                    }
                } catch (e) {
                    console.error('Failed to apply changes:', e);
                    alert('应用配置失败: ' + e.message);
                }
            };

            // Sync Config -> JSON
            watch(config, (newVal) => {
                if (!manualJsonEdit.value) {
                    rawJson.value = toCleanJsonString(newVal);
                }
            }, { deep: true });

            watch(() => config.value.pipelines, (pipes) => {
                (pipes || []).forEach(pipe => {
                    if (pipe._syncInProgress) return;
                    if (!pipe._syncedId) {
                        pipe._syncedId = pipe.id;
                        pipe._oldId = pipe.id;
                        return;
                    }
                    if (pipe._syncedId !== pipe.id) {
                        const safeId = ensureUniquePipelineId(pipe.id, pipe);
                        if (safeId !== pipe.id) {
                            pipe.id = safeId;
                        }
                        replacePipelineRefs(pipe._syncedId, pipe.id);
                        pipe._oldId = pipe.id;
                        pipe._syncedId = pipe.id;
                    }
                });
            }, { deep: true });

            const loadJson = () => {
                try {
                    const parsed = JSON.parse(rawJson.value);
                    (parsed.pipelines || []).forEach(p => {
                        p._oldId = p.id;
                        p._syncedId = p.id;
                        (p.rules || []).forEach(r => normalizeRule(r));
                    });
                    (parsed.pipeline_select || []).forEach(sel => normalizePipelineSelect(sel));
                    config.value = parsed;
                    manualJsonEdit.value = false;
                } catch (e) {
                    alert('JSON 解析失败: ' + e.message);
                }
            };

            const handleFileUpload = (event) => {
                const file = event.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (e) => {
                    rawJson.value = e.target.result;
                    loadJson();
                };
                reader.readAsText(file);
            };

            const downloadJson = () => {
                const blob = new Blob([rawJson.value], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'pipeline.json';
                a.click();
            };

            const addPipelineSelect = () => {
                config.value.pipeline_select.push({ pipeline: '', matchers: [], matcher_operator: DEFAULT_MATCH_OPERATOR });
            };
            const removePipelineSelect = (idx) => {
                config.value.pipeline_select.splice(idx, 1);
            };

            const ensureUniquePipelineId = (base, self = null) => {
                const clean = base && base.trim() ? base.trim() : 'pipeline';
                let candidate = clean;
                let counter = 1;
                while (config.value.pipelines.some(p => p !== self && p.id === candidate)) {
                    candidate = `${clean}_${counter++}`;
                }
                return candidate;
            };

            const generatePipelineId = () => {
                return ensureUniquePipelineId('pipeline');
            };

            const generateRuleName = (pipeline) => {
                const base = 'rule';
                let idx = 1;
                let candidate = `${base}_${idx}`;
                const existing = new Set(pipeline.rules.map(r => r.name));
                while (existing.has(candidate)) {
                    idx += 1;
                    candidate = `${base}_${idx}`;
                }
                return candidate;
            };

            const replacePipelineRefs = (oldId, newId) => {
                if (!oldId || oldId === newId) return;
                config.value.pipeline_select.forEach(sel => {
                    if (sel.pipeline === oldId) sel.pipeline = newId;
                });
                config.value.pipelines.forEach(pipe => {
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
            };

            const normalizeMatcherOps = (items) => {
                (items || []).forEach(m => {
                    if (!m.operator) m.operator = DEFAULT_MATCH_OPERATOR;
                });
            };

            const normalizeRule = (rule) => {
                rule.matchers = rule.matchers || [];
                rule.matcher_operator = rule.matcher_operator || DEFAULT_MATCH_OPERATOR;
                normalizeMatcherOps(rule.matchers);
                rule.actions = rule.actions || [];
                rule.response_matchers = rule.response_matchers || [];
                rule.response_matcher_operator = rule.response_matcher_operator || DEFAULT_MATCH_OPERATOR;
                normalizeMatcherOps(rule.response_matchers);
                rule.response_actions_on_match = rule.response_actions_on_match || [];
                rule.response_actions_on_miss = rule.response_actions_on_miss || [];
            };

            const normalizePipelineSelect = (sel) => {
                sel.matchers = sel.matchers || [];
                sel.matcher_operator = sel.matcher_operator || DEFAULT_MATCH_OPERATOR;
                normalizeMatcherOps(sel.matchers);
            };

            const cacheOldPipelineId = (pipe) => {
                pipe._oldId = pipe.id;
            };

            const markPipelineSync = (pipe) => {
                pipe._syncInProgress = true;
            };

            const clearPipelineSync = (pipe) => {
                pipe._syncInProgress = false;
                pipe._syncedId = pipe.id;
                pipe._oldId = pipe.id;
            };

            const onPipelineIdBlur = (pipe) => {
                const oldId = pipe._oldId || pipe.id;
                markPipelineSync(pipe);
                let newId = (pipe.id || '').trim();
                if (!newId) {
                    alert('Pipeline ID 不能为空，将还原为原值');
                    pipe.id = oldId;
                    clearPipelineSync(pipe);
                    return;
                }
                const uniqueId = ensureUniquePipelineId(newId, pipe);
                if (uniqueId !== newId) {
                    alert(`Pipeline ID 已存在，已调整为 ${uniqueId}`);
                }
                pipe.id = uniqueId;
                if (oldId !== pipe.id) {
                    replacePipelineRefs(oldId, pipe.id);
                    alert(`已将 Pipeline ID 从 ${oldId} 更新为 ${pipe.id}，所有引用已同步`);
                }
                clearPipelineSync(pipe);
            };

            const addPipeline = () => {
                const newId = generatePipelineId();
                config.value.pipelines.push({ id: newId, rules: [], _oldId: newId, _syncedId: newId });
            };
            const removePipeline = (idx) => {
                config.value.pipelines.splice(idx, 1);
            };
            const addRule = (pipeline) => {
                const name = generateRuleName(pipeline);
                const rule = {
                    name,
                    matchers: [],
                    matcher_operator: DEFAULT_MATCH_OPERATOR,
                    actions: [],
                    response_matchers: [],
                    response_matcher_operator: DEFAULT_MATCH_OPERATOR,
                    response_actions_on_match: [],
                    response_actions_on_miss: []
                };
                normalizeRule(rule);
                pipeline.rules.push(rule);
            };
            const removeRule = (pipeline, idx) => {
                pipeline.rules.splice(idx, 1);
            };
            const ruleHasForwardAction = (rule) => {
                return rule.actions.some(action => action.type === 'forward');
            };

            const activePipelineIndex = ref(0);
            const togglePipeline = (idx) => {
                activePipelineIndex.value = activePipelineIndex.value === idx ? -1 : idx;
            };

            const currentTab = ref('editor');
            const mermaidRef = ref(null);

            const summarizeMatchers = (ms) => {
                if (!ms || ms.length === 0) return 'ANY';
                return ms
                    .map((m, idx) => {
                        const op = idx === 0 ? '' : m.operator?.toUpperCase() || 'AND';
                        const type = m.type || 'matcher';
                        const val = m.value || m.cidr || (m.expect === true || m.expect === false ? `expect=${m.expect}` : '');
                        const body = val ? `${type}:${val}` : type;
                        return op ? `${op} ${body}` : body;
                    })
                    .join(' ');
            };

            const renderFlowchart = async () => {
                currentTab.value = 'flowchart';
                await nextTick();
                
                const idMap = {};
                const usedSafeIds = new Set();
                (config.value.pipelines || []).forEach(p => {
                    let safe = makeSafeId(p.id);
                    let counter = 1;
                    while (usedSafeIds.has(safe)) {
                        safe = `${makeSafeId(p.id)}_${counter++}`;
                    }
                    usedSafeIds.add(safe);
                    idMap[p.id] = safe;
                });

                const postEdges = [];
                let graph = "graph TD\n";
                graph += "  Start((Start)) --> Select{Selector}\n";

                config.value.pipelines.forEach(pipe => {
                    const pid = pipe.id;
                    const pidSafe = idMap[pid];
                    const entryId = `${pidSafe}_entry`;
                    graph += `  subgraph ${pidSafe}_sg["${pid}"]\n`;
                    graph += `    direction TB\n`;
                    graph += `    ${entryId}([入口])\n`;

                    const rules = pipe.rules || [];
                    rules.forEach((rule, rIdx) => {
                        const rid = `${pidSafe}_r${rIdx}`;
                        const label = rule.name ? `${rIdx + 1}. ${rule.name}` : `Rule ${rIdx + 1}`;
                        graph += `    ${rid}[[${label}]]\n`;

                        (rule.actions || []).forEach(action => {
                            if (action.type === 'jump_to_pipeline' && action.pipeline) {
                                const targetSafe = idMap[action.pipeline] || makeSafeId(action.pipeline);
                                const targetEntry = `${targetSafe}_entry`;
                                postEdges.push(`  ${rid} -. "jump" .-> ${targetEntry}\n`);
                            }
                        });
                        (rule.response_actions_on_match || []).forEach(action => {
                            if (action.type === 'jump_to_pipeline' && action.pipeline) {
                                const targetSafe = idMap[action.pipeline] || makeSafeId(action.pipeline);
                                const targetEntry = `${targetSafe}_entry`;
                                postEdges.push(`  ${rid} -. "resp match jump" .-> ${targetEntry}\n`);
                            }
                        });
                        (rule.response_actions_on_miss || []).forEach(action => {
                            if (action.type === 'jump_to_pipeline' && action.pipeline) {
                                const targetSafe = idMap[action.pipeline] || makeSafeId(action.pipeline);
                                const targetEntry = `${targetSafe}_entry`;
                                postEdges.push(`  ${rid} -. "resp miss jump" .-> ${targetEntry}\n`);
                            }
                        });

                        const matcherLabel = summarizeMatchers(rule.matchers);
                        if (rIdx === 0) {
                            graph += `    ${entryId} -->|"${matcherLabel}"| ${rid}\n`;
                        } else {
                            const prevId = `${pidSafe}_r${rIdx - 1}`;
                            graph += `    ${prevId} -->|"${matcherLabel}"| ${rid}\n`;
                        }
                    });

                    graph += `  end\n`;
                });

                config.value.pipeline_select.forEach((sel, idx) => {
                    if (sel.pipeline) {
                        const label = summarizeMatchers(sel.matchers);
                        const targetSafe = idMap[sel.pipeline] || makeSafeId(sel.pipeline);
                        const entryId = `${targetSafe}_entry`;
                        graph += `  Select -- "#${idx+1}: ${label}" --> ${entryId}\n`;
                    }
                });

                postEdges.forEach(e => {
                    graph += e;
                });

                if (mermaidRef.value) {
                    mermaidRef.value.innerHTML = '';
                    try {
                        if (typeof mermaid !== 'undefined') {
                            mermaid.initialize({ startOnLoad: false, securityLevel: 'loose' });
                            const { svg } = await mermaid.render('graphDiv' + Date.now(), graph);
                            mermaidRef.value.innerHTML = svg;
                        } else {
                            mermaidRef.value.innerHTML = '<div class="text-danger">Mermaid 未加载</div>';
                        }
                    } catch (e) {
                        mermaidRef.value.innerHTML = '<div class="text-danger">流程图渲染错误: ' + e.message + '</div>';
                        console.error(e);
                    }
                }
            };

            // Initialize on mount
            onMounted(() => {
                loadConfig();
            });

            return {
                config, rawJson, manualJsonEdit, loading, saving,
                selectorMatcherTypes, requestMatcherTypes, responseMatcherTypes,
                loadConfig, saveConfig, applyChanges,
                loadJson, handleFileUpload, downloadJson,
                addPipelineSelect, removePipelineSelect,
                addPipeline, removePipeline,
                addRule, removeRule,
                ruleHasForwardAction,
                activePipelineIndex, togglePipeline,
                currentTab, mermaidRef, renderFlowchart,
                onPipelineIdBlur, cacheOldPipelineId
            };
        }
    }).mount('#kixdns-app');
}

// Auto-init when DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKixDNSEditor);
} else {
    initKixDNSEditor();
}


