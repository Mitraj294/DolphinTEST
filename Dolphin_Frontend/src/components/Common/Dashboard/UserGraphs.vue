<template>
  <div class="user-graphs-outer">
    <div class="user-graphs-card">
      <!-- Header -->
      <div class="summary-header">
        <div class="summary-title">Projection Summary</div>
        <Dropdown mode="radio" :options="attemptOptions" v-model="selectedAttempt" />
      </div>

      <div class="user-graphs-content">
        <!-- Self Wiring -->
        <GraphSection
          title="Self Wiring"
          :count="selfCount"
          :chartOption="originalOption"
          :isReady="isReady"
        />

        <!-- Adapted Self Wiring -->
        <GraphSection
          title="Adapted Self Wiring"
          :count="conceptCount"
          :chartOption="adjustedOption"
          :isReady="isReady"
          :header-extra-text="energizeText"
          :header-extra-class="energizeClass"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick, defineComponent, h } from 'vue';
import Dropdown from '@/components/Common/Common_UI/Dropdown.vue';
import VueECharts from 'vue-echarts';
import axios from 'axios';
import storage from '@/services/storage';
import { getApiBase } from '@/env';
import * as echarts from 'echarts/core';
import { ScatterChart, LineChart } from 'echarts/charts';
import {
  GridComponent,
  TooltipComponent,
  TitleComponent,
  LegendComponent,
  GraphicComponent,
} from 'echarts/components';
import { CanvasRenderer, SVGRenderer } from 'echarts/renderers';

echarts.use([
  ScatterChart,
  LineChart,
  TitleComponent,
  TooltipComponent,
  GridComponent,
  LegendComponent,
  GraphicComponent,
  CanvasRenderer,
  SVGRenderer,
]);

/* ------------------------
   Reusable Child Component (render-function to avoid runtime template compilation)
------------------------ */
const GraphSection = defineComponent({
  name: 'GraphSection',
  props: {
    title: { type: String, required: false },
    count: { type: [String, Number], required: false },
    chartOption: { type: Object, required: false },
    isReady: { type: Boolean, required: false },
    // optional header extra text and class (used for the Energize badge)
    headerExtraText: { type: String, required: false },
    headerExtraClass: { type: String, required: false },
  },
  setup(props) {
    return () =>
      h('div', { class: 'user-graph-section' }, [
        h('div', { class: 'user-graph-header' }, [
          props.title,
          props.headerExtraText
            ? h(
                'div',
                {
                  class: ['energize-badge', props.headerExtraClass],
                  style: {
                    marginLeft: '10px',
                    display: 'inline-flex',
                    alignItems: 'center',
                    padding: '6px 10px',
                    borderRadius: '12px',
                    backgroundColor: (function () {
                      if (props.headerExtraClass === 'energize-yes') return '#2a9d8f';
                      if (props.headerExtraClass === 'energize-no') return '#e63946';
                      return 'transparent';
                    })(),
                    color: '#ffffff',
                    fontWeight: 700,
                    fontSize: '13px',
                    boxShadow: '0 2px 8px rgba(0,0,0,0.08)',
                  },
                },
                props.headerExtraText
              )
            : null,
        ]),
        h('div', { class: 'user-graph-inner' }, [
          props.chartOption && props.chartOption.series && props.chartOption.series.length
            ? h(VueECharts, {
                class: 'echart',
                option: props.chartOption,
                initOptions: { renderer: 'svg' },
                autoresize: true,
                style: { height: '300px', width: '100%', minHeight: '220px' },
              })
            : h('div', { class: 'chart-loading' }, 'No chart data available.'),
        ]),
      ]);
  },
});

/* ------------------------
   Main Component Logic
------------------------ */
const API_BASE_URL = getApiBase();
const attemptOptions = ref([]);
const selectedAttempt = ref(null);
const selectedResult = ref(null);
const rawResults = ref([]);
const isReady = ref(false);

function getToken() {
  const token = storage.get('authToken');
  return token ? { Authorization: `Bearer ${token}` } : {};
}

async function fetchUserResults() {
  const url = `${API_BASE_URL}/api/assessment-results/user`;
  const res = await axios.get(url, { headers: getToken() });
  return res.data?.results || [];
}

async function loadResults() {
  const results = await fetchUserResults();
  console.log('API Results:', results);

  rawResults.value = results;

  // collect unique attempt ids and sort numerically ascending so dropdown shows 1,2,3
  const attemptIds = Array.from(new Set(results.map((r) => r.attempt_id))).sort(
    (a, b) => Number(a) - Number(b)
  );
  attemptOptions.value = attemptIds.map((id) => ({ label: `Attempt ${id}`, value: id }));

  // default to latest attempt (highest id) which is the last after sorting asc
  selectedAttempt.value = attemptIds.length ? attemptIds[attemptIds.length - 1] : null;
  updateSelectedResult();
}

function updateSelectedResult() {
  selectedResult.value =
    rawResults.value.find((r) => String(r.attempt_id) === String(selectedAttempt.value)) || null;
}

watch(selectedAttempt, updateSelectedResult);

onMounted(async () => {
  await loadResults();
  await nextTick();
  isReady.value = true;
});

/* ------------------------
   Chart Data Builders
------------------------ */
function buildProjectionData(r, type = 'original') {
  if (!r) return null;
  const prefix = type === 'concept' ? 'conc_' : 'self_';
  const toPct = (v) => (typeof v === 'number' ? Math.round(v * 100) : 0);
  // Helper to read a possibly-prefixed numeric field reliably
  const readPrefNumber = (obj, key) => {
    const v = obj[key];
    if (v === undefined || v === null) return null;
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
  };

  // Decision approach: try prefixed field, then fall back to other related fields (self_dec_approach), then top-level
  const prefDec = readPrefNumber(r, `${prefix}dec_approach`);
  let decisionRaw = prefDec;
  if (decisionRaw === null && type === 'concept') {
    // try to reuse self_dec_approach if concept-specific not provided
    decisionRaw = readPrefNumber(r, `self_dec_approach`);
  }
  if (decisionRaw === null) decisionRaw = readPrefNumber(r, 'dec_approach');

  // avoid negated condition lint rule by computing decisionPct first
  const decisionPct = (function () {
    if (decisionRaw === null) return 0;
    return toPct(decisionRaw);
  })();

  return {
    collaborative: toPct(r[`${prefix}a`]),
    internalProcessor: toPct(r[`${prefix}b`]),
    urgency: toPct(r[`${prefix}c`]),
    unstructured: toPct(r[`${prefix}d`]),
    decision: decisionPct,
    avg: toPct(r[`${prefix}avg`]),
  };
}

function buildChartOption(r, title, type) {
  if (!r) return null;
  const d = buildProjectionData(r, type);
  const isOriginal = type === 'original';
  const categories = isOriginal
    ? ['Collaborative', 'Internal Processor', 'Urgency', 'Unstructured', 'Decision Approach']
    : ['Collaborative', 'Internal Processor', 'Urgency', 'Unstructured'];
  const right = isOriginal
    ? ['Independent', 'External Processor', 'Methodical', 'Structured', 'Decision Approach']
    : ['Independent', 'External Processor', 'Methodical', 'Structured'];

  // Base points (A-D)
  const basePoints = [
    { value: [d.collaborative, 0], name: 'A' },
    { value: [d.internalProcessor, 1], name: 'B' },
    { value: [d.urgency, 2], name: 'C' },
    { value: [d.unstructured, 3], name: 'D' },
  ];
  // Decision point only on self/original
  const decisionPoint = isOriginal ? { value: [d.decision, 4], name: 'DA' } : null;

  const avg =
    d.avg || Math.round((d.collaborative + d.internalProcessor + d.urgency + d.unstructured) / 4);

  return {
    // Title is rendered by the component header already; avoid duplicating it inside the chart
    // title: { text: title, left: 'center' },
    // increase top padding so we have room for the decision label
    grid: { left: 140, right: 140, top: 50, bottom: 30 },
    xAxis: { type: 'value', min: 0, max: 100, splitLine: { show: true } },
    yAxis: [
      { type: 'category', data: categories, inverse: true },
      { type: 'category', data: right, position: 'right', inverse: true },
    ],
    tooltip: {
      trigger: 'item',
      formatter: (p) => `${categories[p.value[1]]} ⇄ ${right[p.value[1]]}: ${p.value[0]}%`,
    },
    series: [
      {
        name: 'Points',
        type: 'scatter',
        data: basePoints,
        symbol: 'circle',
        symbolSize: 12,
        itemStyle: { color: '#0a74c5', borderColor: '#5470c6', borderWidth: 2 },
        label: {
          show: true,
          formatter: '{b}',
          position: 'right',
          distance: 6,
          color: '#0a74c5',
          fontWeight: '700',
        },

        markLine: {
          silent: true,
          symbol: 'none',
          lineStyle: { type: 'dotted', color: '#d14a61', width: 2 },
          label: { show: true, formatter: `Avg: ${avg}%`, position: 'end', color: '#d14a61' },
          data: [{ xAxis: avg }],
        },
      },
      {
        name: 'Trend',
        type: 'line',
        data: basePoints.map((p) => p.value),
        showSymbol: true,
        symbol: 'circle',
        symbolSize: 10,
        lineStyle: { color: '#0a74c5', width: 2 },
        itemStyle: { color: '#0a74c5' },
        connectNulls: false,
      },
      // Decision approach styled distinctly and not connected by the trend line
      ...(isOriginal
        ? [
            {
              name: 'Decision',
              type: 'scatter',
              data: [decisionPoint],
              symbol: 'circle',
              symbolSize: 22,
              itemStyle: {
                color: '#e76f51',
                borderColor: 'transparent',
                borderWidth: 0,
                shadowBlur: 10,
                shadowColor: 'rgba(0,0,0,0.18)',
              },
              label: {
                show: true,
                formatter: '{b}',
                position: 'inside',
                color: '#000000',
                fontWeight: '800',
                fontSize: 12,
                // optional rounded badge if label position changes later
                backgroundColor: 'transparent',
                borderRadius: 6,
                padding: [2, 6],
              },
              z: 5,
            },
          ]
        : []),
      // Fallback explicit vertical avg line: a dotted line that connects the top and bottom category indices
      {
        name: 'AvgLineExplicit',
        type: 'line',
        data: [
          [avg, 0],
          [avg, categories.length - 1],
        ],
        showSymbol: false,
        lineStyle: { type: 'dotted', color: '#222', width: 2 },
        silent: true,
        z: 10,
      },
    ],
  };
}

/* ------------------------
   Computed Properties
------------------------ */
const originalOption = computed(() =>
  buildChartOption(selectedResult.value, 'Original Self', 'original')
);
const adjustedOption = computed(() =>
  buildChartOption(selectedResult.value, 'Adapted Self', 'concept')
);
const selfCount = computed(
  () =>
    selectedResult.value?.self_total_count || selectedResult.value?.self_total_words?.length || null
);
const conceptCount = computed(
  () =>
    selectedResult.value?.conc_total_count || selectedResult.value?.conc_total_words?.length || null
);

// Energize comparison: if concept average > self average => Energized (green), if less => De-energized (red)
const concAvg = computed(() => {
  const d = buildProjectionData(selectedResult.value, 'concept');
  return d?.avg ?? null;
});
const selfAvg = computed(() => {
  const d = buildProjectionData(selectedResult.value, 'original');
  return d?.avg ?? null;
});

const energizeText = computed(() => {
  if (concAvg.value === null || selfAvg.value === null) return null;
  if (concAvg.value > selfAvg.value) return 'Energized';
  if (concAvg.value < selfAvg.value) return 'De-energized';
  return null;
});

const energizeClass = computed(() => {
  if (!energizeText.value) return '';
  return concAvg.value > selfAvg.value ? 'energize-yes' : 'energize-no';
});
</script>

<style scoped>
.user-graphs-outer {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-sizing: border-box;
}

.user-graphs-card {
  width: 100%;
  background: #fff;
  border-radius: 24px;
  border: 1px solid #ebebeb;
  box-shadow: 0 2px 16px 0 rgba(33, 150, 243, 0.04);
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  position: relative;
}

.summary-title {
  font-size: 24px;
  font-weight: 700;
  text-align: left;
  padding: 0;
  color: #1f2d3d;
}

.summary-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 12px 0 12px;
}

.summary-controls {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-graphs-content {
  display: flex;
  flex-direction: row;
  gap: 32px;
  justify-content: center;
  width: 100%;
  padding: 32px 0;
}

.user-graph-section {
  background: transparent;
  border-radius: 16px;
  padding: 32px;
  min-width: 340px;
  flex: 1 1 0;
  display: flex;
  flex-direction: column;
}

.user-graph-header {
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 18px;
  text-align: center;
}

.user-graph-inner {
  background: #f8f8f8;
  border-radius: 16px;
  box-shadow: 0 1px 8px rgba(33, 150, 243, 0.08);
  padding: 24px;
  display: flex;
  flex-direction: column;
}

.echart {
  width: 100%;
  height: 260px;
}

.chart-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 260px;
  font-size: 14px;
  color: #555;
}

.count-badge {
  background: #eef2f7;
  color: #234056;
  border: 1px solid #dfe7f2;
  border-radius: 10px;
  padding: 4px 10px;
  font-weight: 700;
  font-size: 14px;
}

@media (max-width: 1100px) {
  .user-graphs-content {
    flex-direction: column;
    gap: 12px;
  }

  .user-graph-section {
    border-radius: 10px;
    padding: 12px;
  }
}

.energize-badge {
  margin-left: 12px;
  align-self: center;
  padding: 6px 12px;
  border-radius: 14px;
  font-weight: 700;
  font-size: 14px;
  color: #fff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.energize-yes {
  background-color: #2a9d8f;
}
.energize-no {
  background-color: #e63946;
}
</style>
