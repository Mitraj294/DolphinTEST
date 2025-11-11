<template>
  <div class="user-graphs-outer">
    <div class="user-graphs-card">
      <div class="user-graphs-content">

        <!-- Original Self Section -->
        <div class="user-graph-section">
          <div class="user-graph-header">Original Self</div>
          <div class="user-graph-inner">
            <div class="user-graph-inner-header">
              <Dropdown mode="radio" :options="attemptOptions" v-model="selectedAttempt" />
            </div>

            <!-- Chart Loader + Guard -->
            <template v-if="isReady && originalOption && originalOption.series && originalOption.series[0].data">
              <VueECharts
                class="echart"
                :option="originalOption"
                :init-options="{ renderer: 'canvas' }"
                autoresize
              />
            </template>
            <div v-else class="chart-loading">Loading chart...</div>
          </div>
        </div>

        <!-- Adjusted Self Section -->
        <div class="user-graph-section">
          <div class="user-graph-header">Adjusted Self</div>
          <div class="user-graph-inner">
            <div class="user-graph-inner-header">
              <Dropdown mode="radio" :options="attemptOptions" v-model="selectedAttempt" />
            </div>

            <!-- Chart Loader + Guard -->
            <template v-if="isReady && adjustedOption && adjustedOption.series && adjustedOption.series[0].data">
              <VueECharts
                class="echart"
                :option="adjustedOption"
                :init-options="{ renderer: 'canvas' }"
                autoresize
              />
            </template>
            <div v-else class="chart-loading">Loading chart...</div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, nextTick } from "vue";
import Dropdown from "@/components/Common/Common_UI/Dropdown.vue";
import { fetchUserResults, normalizeBarData } from "@/services/results";
import VueECharts from "vue-echarts";
import * as echarts from "echarts/core";
import { BarChart } from "echarts/charts";
import { GridComponent, TooltipComponent, LegendComponent, TitleComponent } from "echarts/components";
import { CanvasRenderer } from "echarts/renderers";

echarts.use([BarChart, GridComponent, TooltipComponent, LegendComponent, TitleComponent, CanvasRenderer]);

// Reactive state
const attemptOptions = ref([]);
const selectedAttempt = ref(null);
const latestOriginal = ref(null);
const latestAdjusted = ref(null);
const loading = ref(false);
const error = ref(null);
const rawResults = ref([]);
const isReady = ref(false);

// Load user results
async function loadResults() {
  loading.value = true;
  error.value = null;
  try {
    const results = await fetchUserResults();
    rawResults.value = Array.isArray(results) ? results : [];

    // Build attempt dropdown
    const byAttempt = {};
    for (const r of rawResults.value) {
      if (!byAttempt[r.attempt_id]) byAttempt[r.attempt_id] = [];
      byAttempt[r.attempt_id].push(r);
    }

    attemptOptions.value = Object.keys(byAttempt)
      .sort((a, b) => Number(a) - Number(b))
      .map((id) => ({ label: `Attempt ${id}`, value: id }));

    // Select latest attempt
    if (attemptOptions.value.length > 0 && !selectedAttempt.value) {
      selectedAttempt.value = attemptOptions.value[attemptOptions.value.length - 1].value;
    }

    updateSelectedData();
  } catch (e) {
    error.value = e?.message || String(e);
  } finally {
    loading.value = false;
  }
}

// Update chart data when attempt changes
function updateSelectedData() {
  const attemptId = selectedAttempt.value;
  if (!attemptId) return;
  const attemptResults = rawResults.value.filter(r => String(r.attempt_id) === String(attemptId));

  latestOriginal.value = attemptResults.find(r => r.type === "original") || null;
  const adjustCandidates = attemptResults.filter(r => r.type === "adjust");
  latestAdjusted.value = adjustCandidates.length ? adjustCandidates[adjustCandidates.length - 1] : null;
}

watch(selectedAttempt, updateSelectedData);

// Wait for DOM to be ready before initializing charts
onMounted(async () => {
  await loadResults();
  await nextTick();
  setTimeout(() => {
    isReady.value = true;
  }, 100);
});

// Chart options
const originalOption = computed(() => {
  const data = normalizeBarData(latestOriginal.value)?.original;
  if (!data || Object.values(data).some(v => v == null)) return null;
  return buildBarOption(data, "Original Self");
});

const adjustedOption = computed(() => {
  const data = normalizeBarData(latestAdjusted.value)?.adjusted;
  if (!data || Object.values(data).some(v => v == null)) return null;
  return buildBarOption(data, "Adjusted Self");
});

// Bar chart builder
function buildBarOption(barData, title) {
  return {
    title: { text: title, left: "center", textStyle: { fontSize: 14 } },
    grid: { left: 40, right: 10, top: 30, bottom: 30 },
    xAxis: { type: "category", data: ["A", "B", "C", "D"] },
    yAxis: { type: "value", max: 100 },
    tooltip: { trigger: "axis" },
    series: [
      {
        type: "bar",
        data: [barData.a, barData.b, barData.c, barData.d],
        itemStyle: { color: "#0164A5" },
        barWidth: "45%",
      },
    ],
  };
}
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
  text-align: left;
}

.user-graph-inner {
  background: #f8f8f8;
  border-radius: 16px;
  box-shadow: 0 1px 8px rgba(33, 150, 243, 0.08);
  padding: 24px;
  display: flex;
  flex-direction: column;
}

.user-graph-inner-header {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 8px;
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

/* Responsive */
@media (max-width: 1400px) {
  .user-graphs-content {
    gap: 18px;
    padding: 18px 0;
  }
  .user-graph-section {
    border-radius: 14px;
    padding: 18px;
  }
}

@media (max-width: 900px) {
  .user-graphs-content {
    flex-direction: column;
    gap: 12px;
  }
  .user-graph-section {
    border-radius: 10px;
    padding: 12px;
  }
}
</style>
