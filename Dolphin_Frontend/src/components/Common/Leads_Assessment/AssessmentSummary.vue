<template>
  <MainLayout>
    <div class="page">
      <div class="table-outer">
        <div class="assessment-table-card">
          <div class="assessment-summary-cards">
            <div class="assessment-summary-card">
              <div class="summary-label">Total Sent Assessment</div>
              <div class="summary-value">{{ summary.total_sent }}</div>
            </div>
            <div class="assessment-summary-card">
              <div class="summary-label">Submitted Assessment</div>
              <div class="summary-value">{{ summary.submitted }}</div>
            </div>
            <div class="assessment-summary-card">
              <div class="summary-label">Pending</div>
              <div class="summary-value">{{ summary.pending }}</div>
            </div>
          </div>
          <div class="assessment-table-header-spacer"></div>
          <div class="assessment-table-container">
            <div class="table-scroll">
              <table class="assessment-table">
                <TableHeader :columns="tableColumns" @sort="sortBy" />
                <tbody>
                  <tr v-for="row in paginatedRows" :key="row.memberId || row.name">
                    <td class="member-name-td">
                      <div class="member-name-main">{{ row.name }}</div>
                      <div class="member-meta">
                        <span v-if="row.email" class="member-meta-item">{{ row.email }}</span>
                        <span v-if="row.lastSubmittedAt" class="member-meta-item">
                          Last Submitted: {{ formatTimestamp(row.lastSubmittedAt) }}
                        </span>
                      </div>
                    </td>
                    <td>
                      <span v-if="row.result === 'Submitted'" class="status submitted">
                        <svg
                          width="20"
                          height="20"
                          viewBox="0 0 20 20"
                          fill="none"
                          style="margin-right: 8px"
                        >
                          <circle cx="10" cy="10" r="10" fill="#48B02C" />
                          <path
                            d="M6 10.5L9 13.5L14 8.5"
                            stroke="white"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                          />
                        </svg>
                        Submitted
                      </span>
                      <span v-else class="status pending">
                        <span
                          style="
                            position: relative;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 20px;
                            height: 20px;
                            margin-right: 8px;
                          "
                        >
                          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="10" fill="#F0F0F0" />
                          </svg>
                          <img
                            src="@/assets/images/Pending.svg"
                            alt="Pending"
                            style="
                              position: absolute;
                              left: 3px;
                              top: 3px;
                              width: 14px;
                              height: 14px;
                            "
                          />
                        </span>
                        Pending
                      </span>
                    </td>
                    <td>
                      <button class="btn-view" @click="openModal(row)">
                        <img
                          src="@/assets/images/Notes.svg"
                          alt="View"
                          class="btn-view-icon"
                          width="18"
                          height="18"
                        />
                        View
                      </button>
                    </td>
                  </tr>
                  <tr v-if="paginatedRows.length === 0">
                    <td colspan="3" class="no-data">No assessments found.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <Pagination
          :pageSize="pageSize"
          :pageSizes="pageSizes"
          :showPageDropdown="showPageDropdown"
          :currentPage="currentPage"
          :totalPages="totalPages"
          @goToPage="goToPage"
          @selectPageSize="selectPageSize"
          @togglePageDropdown="togglePageDropdown"
        />
        <!-- Modal for assessment details -->
        <div v-if="showModal" class="assessment-modal-overlay" @click.self="closeModal">
          <div class="assessment-modal-content">
            <div class="assessment-modal-header-row sticky-modal-header">
              <div>
                <h2>
                  {{
                    selectedMember.name ? `${selectedMember.name}’s Attempts` : 'Member Attempts'
                  }}
                </h2>
                <div class="modal-member-meta">
                  <span v-if="selectedMember.email">{{ selectedMember.email }}</span>
             
                  <span v-if="selectedMember.lastSubmittedAt">
                    Last Submitted: {{ formatTimestamp(selectedMember.lastSubmittedAt) }}
                  </span>
                </div>
              </div>
              <button class="btn modal-close-btn" @click="closeModal">&times;</button>
            </div>
            <div class="assessment-modal-scrollable">
              <div v-if="selectedMember.attempts && selectedMember.attempts.length">
                <div
                  v-for="(attempt, idx) in selectedMember.attempts"
                  :key="attempt.attempt_id || idx"
                  class="attempt-block"
                >
                  <div class="attempt-header">
                    <span>Attempt {{ idx + 1 }}</span>
                    <span v-if="attempt.submitted_at">
                      • {{ formatTimestamp(attempt.submitted_at) }}
                    </span>
                  </div>
                  <div class="attempt-graph">
                    <div class="attempt-graph-grid">
                      <span
                        v-for="(option, optIdx) in attempt.selected_options || []"
                        :key="`${attempt.attempt_id || idx}-${optIdx}`"
                        class="attempt-graph-chip"
                      >
                        <span class="attempt-graph-indicator"></span>
                        <span>{{ option }}</span>
                      </span>
                      <div
                        v-if="!(attempt.selected_options && attempt.selected_options.length)"
                        class="attempt-no-options"
                      >
                        No options were recorded for this attempt.
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="attempt-no-submissions">No submissions yet for this member.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script>
import TableHeader from '@/components/Common/Common_UI/TableHeader.vue';
import MainLayout from '@/components/layout/MainLayout.vue';
import Pagination from '@/components/layout/Pagination.vue';
import axios from 'axios';

export default {
  name: 'AssessmentSummaryPage',
  components: { MainLayout, Pagination, TableHeader },
  data() {
    return {
      assessmentId: null,
      organizationAssessmentId: null,
      rows: [],
      summary: { total_sent: 0, submitted: 0, pending: 0 },
      tableColumns: [
        { label: 'Member Name', key: 'name' },
        { label: 'Result', key: 'result' },
        { label: 'Actions', key: 'actions' },
      ],
      pageSizes: [10, 25, 100],
      pageSize: 10,
      currentPage: 1,
      showPageDropdown: false,
      showModal: false,
      selectedMember: { name: '', attempts: [] },
    };
  },
  computed: {
    totalPages() {
      return Math.max(1, Math.ceil(this.rows.length / this.pageSize));
    },
    paginatedRows() {
      const start = (this.currentPage - 1) * this.pageSize;
      return this.rows.slice(start, start + this.pageSize);
    },
  },
  methods: {
    async fetchSummary() {
      if (!this.assessmentId) return;
      try {
        const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;
        const params = {};
        if (this.organizationAssessmentId) {
          params.organization_assessment_id = this.organizationAssessmentId;
        } else if (this.assessmentId) {
          params.organization_assessment_id = this.assessmentId;
        }
        const res = await axios.get(
          `${API_BASE_URL}/api/assessments/${this.assessmentId}/summary`,
          {
            params,
          }
        );
        const data = res && res.data ? res.data : {};
        const orgAssessment = data.organization_assessment || {};
        const assessmentName =
          orgAssessment.name ||
          orgAssessment.organization_assessment_name ||
          orgAssessment.title ||
          null;
        if (assessmentName && this.$root && this.$root.$emit) {
          this.$root.$emit('page-title-override', `Assessment ${assessmentName} Summary`);
        }

        const members = Array.isArray(data.members) ? data.members : [];
        this.rows = members.map((member) => ({
          memberId: member.member_id ?? member.user_id,
          name:
            member.name ||
            member.email ||
            (member.member_id
              ? `Member #${member.member_id}`
              : `Member ${member.user_id || 'Unknown'}`),
          email: member.email || '',
          status: member.status || '',
          result: member.submitted ? 'Submitted' : 'Pending',
          submitted: !!member.submitted,
          lastSubmittedAt: member.last_submitted_at,
          attempts: Array.isArray(member.attempts) ? member.attempts : [],
        }));
        this.summary = data.summary || {
          total_sent: 0,
          submitted: 0,
          pending: 0,
        };
          if (data && this.$root && this.$root.$emit && this.assessmentId) {
            this.$root.$emit('assessment-summary-data', {
              assessmentId: this.assessmentId,
              data,
            });
          }
      } catch (e) {
        this.rows = [];
        this.summary = { total_sent: 0, submitted: 0, pending: 0 };
        console.debug && console.debug('Failed to fetch assessment summary:', e);
      }
    },
    openModal(row) {
      this.selectedMember = {
        name: row.name,
        email: row.email,
        status: row.status,
        attempts: Array.isArray(row.attempts) ? row.attempts : [],
        lastSubmittedAt: row.lastSubmittedAt,
      };
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.selectedMember = { name: '', attempts: [] };
    },
    goToPage(page) {
      if (page >= 1 && page <= this.totalPages) {
        this.currentPage = page;
      }
    },
    selectPageSize(size) {
      this.pageSize = size;
      this.currentPage = 1;
      this.showPageDropdown = false;
    },
    togglePageDropdown() {
      this.showPageDropdown = !this.showPageDropdown;
    },
    sortBy() {
      // No-op
    },
    formatTimestamp(value) {
      if (!value) return '';
      const parsed = new Date(value);
      if (Number.isNaN(parsed.getTime())) return value;
      return parsed.toLocaleString();
    },
    getRouteOrganizationAssessmentId(route) {
      if (!route) return null;
      return (
        (route.params &&
          (route.params.organizationAssessmentId || route.params.organization_assessment_id)) ||
        (route.query &&
          (route.query.organizationAssessmentId || route.query.organization_assessment_id)) ||
        null
      );
    },
  },
  watch: {
    $route(to) {
      const nextAssessmentId = to.params.assessmentId;
      const nextOrgId = this.getRouteOrganizationAssessmentId(to);
      if (nextAssessmentId === this.assessmentId && nextOrgId === this.organizationAssessmentId) {
        return;
      }
      this.assessmentId = nextAssessmentId;
      this.organizationAssessmentId = nextOrgId;
      this.fetchSummary();
    },
  },
  created() {
    this.assessmentId = this.$route.params.assessmentId;
    this.organizationAssessmentId = this.getRouteOrganizationAssessmentId(this.$route);
    this.fetchSummary();
  },
  beforeDestroy() {
    if (this.$root && this.$root.$emit) {
      this.$root.$emit('page-title-override', null);
    }
  },
};
</script>

<style scoped>
.assessment-table-outer {
  width: 100%;
  min-width: 260px;

  display: flex;
  flex-direction: column;
  align-items: center;
  box-sizing: border-box;
}
.assessment-table-card {
  width: 100%;
  background: #fff;
  border-radius: 24px;
  border: 1px solid #ebebeb;
  box-shadow: 0 2px 16px 0 rgba(33, 150, 243, 0.04);
  overflow: visible;
  margin: 0 auto;
  box-sizing: border-box;
  min-width: 0;

  display: flex;
  flex-direction: column;
  gap: 0;
  position: relative;
}
.assessment-summary-cards {
  display: flex;
  gap: 16px;
  margin-bottom: 0;
  flex-wrap: wrap;
  justify-content: flex-start;
  padding: 26px;
  background: #fff;
  border-top-left-radius: 24px;
  border-top-right-radius: 24px;
  min-height: 64px;
  box-sizing: border-box;
}
.assessment-summary-card {
  background: #f8f8f8;
  border-radius: 16px;
  padding: 18px 16px;
  min-width: 90px;
  flex: 1 1 90px;
  text-align: center;
  margin: 0 0 12px 0;
  box-sizing: border-box;
}
.summary-label {
  color: #888;
  font-size: 16px;
  margin-bottom: 8px;
}
.summary-value {
  font-size: 32px;
  font-weight: 700;
  color: #222;
}
.assessment-table-header-spacer {
  height: 18px;
  width: 100%;
  background: transparent;
  display: block;
}
.assessment-table-container {
  width: 100%;
  overflow-x: auto;
  box-sizing: border-box;
  padding: 0 24px 24px 24px;
  background: #fff;
  border-bottom-left-radius: 24px;
  border-bottom-right-radius: 24px;
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.assessment-table-container::-webkit-scrollbar {
  display: none;
}
.assessment-table {
  min-width: 500px;
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin-bottom: 8px;
  background: transparent;
  margin-left: 0;
  margin-right: 0;
  table-layout: auto;
  border: none;
  margin-top: 0;
}
.assessment-table th,
.assessment-table td {
  padding: 12px 8px;
  text-align: left;
  font-size: 14px;
  border-bottom: 1px solid #f0f0f0;
  background: #fff;
}
.assessment-table th:first-child {
  padding-left: 20px !important;
}
.assessment-table th {
  background: #f8f8f8;
  font-weight: 600;
  color: #333;
  position: relative;
  vertical-align: middle;
  min-width: 100px;
}
.rounded-th-left {
  border-top-left-radius: 24px;
  border-bottom-left-radius: 24px;
  overflow: hidden;
  background: #f8f8f8;
  padding-left: 20px !important;
}
.rounded-th-right {
  border-top-right-radius: 24px;
  border-bottom-right-radius: 24px;
  overflow: hidden;
  background: #f8f8f8;
}
.assessment-table td {
  color: #222;
  background: #fff;
}
.status {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
}
.status.submitted {
  color: #48b02c;
}
.status.pending {
  color: #5d5d5d;
}

.no-data {
  text-align: center;
  color: #888;
  font-size: 16px;
  padding: 32px 0;
}
/* Modal styles - match base modal style */
.assessment-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.25);
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
}
.assessment-modal-content {
  background: #fff;
  border-radius: 12px;
  padding: 0;
  min-width: 480px;
  max-width: 600px;
  box-shadow: 0 4px 32px rgba(0, 0, 0, 0.12);
  display: flex;
  flex-direction: column;
  align-items: stretch;
  position: relative;
  max-height: 65vh;
  overflow: hidden;
}
.assessment-modal-header-row {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 0;
  padding: 16px 32px 10px 32px;
  background: #fff;
  z-index: 2;
}
.sticky-modal-header {
  position: sticky;
  top: 0;
  left: 0;
  right: 0;
  background: #fff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}
.assessment-modal-header-row h2 {
  font-size: 1.45rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  color: #222;
  margin: 0;
  padding: 0;
  flex: 1 1 0;
  text-align: left;
}
.assessment-modal-header-row .modal-close-btn {
  margin-left: 16px;
  margin-top: 0;
  font-size: 2rem;
  line-height: 1;
  padding: 0 8px;
  height: 32px;
  width: 32px;
  min-width: 32px;
  min-height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.assessment-modal-scrollable {
  overflow-y: auto;
  padding: 24px 32px 24px 32px;
  flex: 1 1 auto;
  max-height: calc(65vh - 80px);
}
.assessment-question-block {
  margin-bottom: 24px;
  text-align: left;
}
.assessment-question {
  font-weight: 600;
  font-size: 1.08rem;
  margin-bottom: 10px;
  color: #222;
  letter-spacing: 0.01em;
  text-align: left;
}
.assessment-answer {
  background: #f8f8f8;
  border-radius: 10px;
  padding: 13px 18px;
  font-size: 1rem;
  color: #222;
  margin-bottom: 2px;
  font-weight: 500;
  line-height: 1.5;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
  text-align: left;
}
/* Additional layout tweaks for the updated member rows and modal */
.member-name-main {
  font-weight: 600;
}
.member-meta {
  font-size: 0.85rem;
  color: #666;
  display: flex;
  flex-direction: column;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
}
.member-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.modal-member-meta {
  font-size: 0.9rem;
  color: #666;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 6px;
}
.attempt-block {
  margin-bottom: 24px;
}
.attempt-header {
  font-weight: 600;
  margin-bottom: 8px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: center;
}
.attempt-graph {
  margin-bottom: 16px;
}
.attempt-graph-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.attempt-graph-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  background: #e8f0fe;
  color: #1b3e66;
  font-weight: 500;
  font-size: 0.95rem;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.05);
}
.attempt-graph-indicator {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #0074c2;
  display: inline-block;
}
.attempt-no-options,
.attempt-no-submissions {
  color: #7c7c7c;
  font-style: italic;
}
/* Responsive styles to match base pages */

.member-name-td {
  padding-left: 20px !important;
}
</style>
