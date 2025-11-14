<template>
  <div>
    <div class="assessments-card">
      <div class="assessments-header-row">
        <div class="assessments-header-actions">
          <button class="btn btn-primary" @click="showCreateModal = true">
            <img src="@/assets/images/Add.svg" alt="Add" class="btn-icon" />
            Create Assessments
          </button>
        </div>
      </div>

      <div class="table-container">
        <table class="table">
          <TableHeader
            :columns="[
              { label: 'Assessment Name', key: 'name' },
              { label: 'Actions', key: 'actions' },
            ]"
          />
          <tbody>
            <tr v-for="item in paginatedAssessments" :key="item.id">
              <td>
                <button class="assessment-link" @click="goToSummary(item)">
                  {{ item.name }}
                </button>
              </td>
              <td>
                <div v-if="item.schedule" class="scheduled-details"></div>
                <button
                  class="schedule-btn"
                  :id="'schedule-btn-' + item.id"
                  @click="onScheduleButtonClick(item)"
                >
                  <img
                    src="@/assets/images/Schedule.svg"
                    :alt="item.schedule ? 'Details' : 'Schedule'"
                    class="btn-icon"
                  />
                  {{ item.schedule ? 'Details' : 'Schedule' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Modals -->
      <CreateAssessmentModal
        v-if="showCreateModal"
        @close="closeCreateModal"
        @assessment-created="handleAssessmentCreated"
        @validation-error="handleValidationError"
        @error="handleError"
      />

      <div v-if="showScheduleModal" class="modal-overlay">
        <ScheduleAssessmentModal
          :assessmentName="selectedAssessment?.name"
          :assessment_id="selectedAssessment?.id"
          @close="closeScheduleModal"
          @schedule="handleScheduleAssessment"
        />
      </div>

      <ScheduleDetailsModal
        v-if="showScheduleDetailsModal"
        :scheduleDetails="scheduleDetails"
        :allGroups="allGroups"
        :allMembers="allMembers"
        @close="closeScheduleDetailsModal"
      />
    </div>

    <Pagination
      :pageSize="pageSize"
      :pageSizes="[10, 25, 100]"
      :showPageDropdown="showPageDropdown"
      :currentPage="currentPage"
      :totalPages="totalPages"
      @togglePageDropdown="showPageDropdown = !showPageDropdown"
      @selectPageSize="selectPageSize"
      @goToPage="goToPage"
    />
  </div>
</template>

<script>
import TableHeader from '@/components/Common/Common_UI/TableHeader.vue';
import CreateAssessmentModal from '@/components/Common/Leads_Assessment/CreateAssessmentModal.vue';
import ScheduleAssessmentModal from '@/components/Common/Leads_Assessment/ScheduleAssessmentModal.vue';
import ScheduleDetailsModal from '@/components/Common/Leads_Assessment/ScheduleDetailsModal.vue';
import Pagination from '@/components/layout/Pagination.vue';
import storage from '@/services/storage';
import axios from 'axios';
import { useToast } from 'primevue/usetoast';

export default {
  name: 'OrganizationAdminAssessmentsCard',
  components: {
    Pagination,
    ScheduleAssessmentModal,
    ScheduleDetailsModal,
    CreateAssessmentModal,
    TableHeader,
  },
  data() {
    return {
      assessments: [],
      // questions removed — assessments can be created without attaching questions
      allGroups: [],
      allMembers: [],
      selectedAssessment: null,
      scheduleDetails: null,
      showCreateModal: false,
      showScheduleModal: false,
      showScheduleDetailsModal: false,
      loading: false,
      toast: null,
      // Pagination
      pageSize: 10,
      currentPage: 1,
      showPageDropdown: false,
    };
  },
  computed: {
    paginatedAssessments() {
      const start = (this.currentPage - 1) * this.pageSize;
      return this.assessments.slice(start, start + this.pageSize);
    },
    totalPages() {
      return Math.ceil(this.assessments.length / this.pageSize) || 1;
    },
    groupNameMap() {
      return (this.allGroups || []).reduce((map, g) => {
        map[g.id] = g.name;
        return map;
      }, {});
    },
    memberNameMap() {
      return (this.allMembers || []).reduce((map, m) => {
        const first = (m.first_name || m.name || '').toString().trim();
        const last = (m.last_name || '').toString().trim();
        const role = (m.member_role || m.role || '').toString().trim();
        let full = first;
        if (last) full = full ? `${full} ${last}` : last;
        if (!full) full = m.email || m.id || 'Unknown';
        if (role) full = `${full} — ${role}`;
        map[m.id] = full;
        return map;
      }, {});
    },
    memberDetailMap() {
      return (this.allMembers || []).reduce((map, m) => {
        const first = (m.first_name || m.name || '').toString().trim();
        const last = (m.last_name || '').toString().trim();
        let name = first;
        if (last) name = name ? `${name} ${last}` : last;
        if (!name) name = m.email || `Member ${m.id}`;
        map[m.id] = {
          name,
          email: m.email || '',
          role: (m.member_role || m.role || '').toString().trim() || '',
        };
        return map;
      }, {});
    },
  },
  created() {
    this.toast = useToast();
  },
  mounted() {
    this.initializeComponent();
  },
  methods: {
    // --- Data Fetching & Initialization ---
    async initializeComponent() {
      this.loading = true;
      try {
        const authToken = storage.get('authToken');
        const params = await this.getRequestParams(authToken);
        if (!params.organization_id && !params.user_id) {
          this._showToast('warn', 'Missing context', 'No organization or user id found.');
          return;
        }

        await this.fetchData(authToken, params);
      } catch (err) {
        console.debug &&
          console.debug('[AssessmentsCard] Failed to fetch initial data', err?.message || err);
        this.assessments = [];
        this._showToast('error', 'Error', 'Could not load assessment data.');
      } finally {
        this.loading = false;
      }
    },
    async getRequestParams(authToken) {
      // Prefer explicit organization id keys in storage; fall back to profile when needed
      const userId = storage.get('user_id');
      let orgId =
        storage.get('organization_id') ||
        storage.get('org_id') ||
        storage.get('organizationId') ||
        storage.get('orgId') ||
        (storage.get('user') && storage.get('user').organization_id) ||
        null;

      if (!orgId && authToken) {
        try {
          const base = process.env.VUE_APP_API_BASE_URL;
          const profileRes = await axios.get(`${base}/api/profile`, {
            headers: { Authorization: `Bearer ${authToken}` },
          });
          const prof = profileRes.data || {};
          orgId = prof.organization_id || (prof.user && prof.user.organization_id) || null;
        } catch (e) {
          // non-fatal: we'll try user_id next
          console.debug('[AssessmentsCard] profile fetch failed', e?.message || e);
        }
      }

      const params = {};
      if (orgId) params.organization_id = orgId;
      else if (userId) params.user_id = userId;

      return params;
    },
    async fetchData(authToken, params) {
      const base = process.env.VUE_APP_API_BASE_URL;
      const headers = authToken ? { Authorization: `Bearer ${authToken}` } : {};

      try {
        const [assessmentsRes, groupsRes, membersRes] = await Promise.all([
          axios.get(`${base}/api/assessments`, { headers, params }),
          axios.get(`${base}/api/groups`, { headers }),
          axios.get(`${base}/api/organization/members`, { headers }),
        ]);

        this.assessments = this.parseApiResponse(assessmentsRes, 'assessments');
        this.allGroups = this.parseApiResponse(groupsRes, 'groups');
        this.allMembers = this.parseApiResponse(membersRes, 'data');
      } catch (e) {
        console.debug && console.debug('[AssessmentsCard] fetchData error', e?.message || e);
        this.assessments = [];
        this.allGroups = [];
        this.allMembers = [];
      }

      await this.fetchScheduleStatuses(authToken);
    },
    parseApiResponse(response, key) {
      const data = response?.data;
      if (!data) return [];
      if (key && Array.isArray(data[key])) return data[key];
      if (Array.isArray(data)) return data;
      return typeof data === 'object' ? [data] : [];
    },
    async fetchScheduleStatuses() {
      // Attempt to enrich assessments with schedule metadata. Non-fatal on failure.
      try {
        const promises = this.assessments.map((assessment) =>
          this.fetchScheduleForAssessment(assessment).catch(() => ({ ...assessment }))
        );
        const results = await Promise.all(promises);
        this.assessments = results;
      } catch (err) {
        console.debug &&
          console.debug('[AssessmentsCard] fetchScheduleStatuses encountered an error', err);
      }
    },
    async fetchScheduleForAssessment(assessment) {
      // Try to fetch schedule info for the given assessment. If no schedule endpoint
      // is available on the backend this will safely return the original assessment.
      try {
        const base = process.env.VUE_APP_API_BASE_URL;
        const authToken = storage.get('authToken');
        const headers = authToken ? { Authorization: `Bearer ${authToken}` } : {};
        const res = await axios.get(`${base}/api/assessment-schedules`, {
          headers,
          params: { assessment_id: assessment.id },
        });
        const data = res?.data;
        // If backend returns an array of schedules, pick the first one; otherwise accept object
        const schedule = Array.isArray(data) ? data[0] || null : data || null;
        return { ...assessment, schedule };
      } catch (e) {
        // Non-fatal: backend may not support this endpoint. Return assessment unchanged.
        console.debug &&
          console.debug('[AssessmentsCard] fetchScheduleForAssessment failed', e?.message || e);
        return { ...assessment };
      }
    },

    // --- Modal Control ---
    openScheduleModal(item) {
      this.selectedAssessment = item;
      this.showScheduleModal = true;
    },
    closeScheduleModal() {
      this.showScheduleModal = false;
      this.selectedAssessment = null;
    },
    openScheduleDetails(item) {
      this.selectedAssessment = item;
      // Pass a normalized object the modal expects: { assessment, schedule }
      this.scheduleDetails = {
        assessment: { id: item.id, name: item.name },
        schedule: item.schedule || null,
      };
      this.showScheduleDetailsModal = true;
    },
    closeScheduleDetailsModal() {
      this.showScheduleDetailsModal = false;
      this.scheduleDetails = null;
    },
    closeCreateModal() {
      this.showCreateModal = false;
    },

    // --- Event Handlers ---
    onScheduleButtonClick(item) {
      if (item?.schedule) {
        this.openScheduleDetails(item);
      } else {
        this.openScheduleModal(item);
      }
    },
    handleAssessmentCreated(newAssessment) {
      this.assessments.unshift(newAssessment);
      this.closeCreateModal();
    },
    handleValidationError(errorData) {
      this._showToast(errorData.type, errorData.title, errorData.message, 4000, true);
    },
    handleError(errorData) {
      this._showToast(errorData.type, errorData.title, errorData.message);
    },
    async handleScheduleAssessment(payload) {
      // payload expected: { assessment_id, date, time, group_ids, member_ids, selectedMembers }
      const date = payload?.date;
      const time = payload?.time;
      const groupIds = payload?.group_ids || payload?.groupIds || [];
      const memberIds = payload?.member_ids || payload?.memberIds || [];
      const selectedMembers = payload?.selectedMembers || [];

      if (!this.selectedAssessment || !date || !time) {
        return this._showToast('warn', 'Missing Data', 'Please select assessment, date, and time.');
      }

      this.loading = true;
      const authToken = storage.get('authToken');
      const base = process.env.VUE_APP_API_BASE_URL;
      const headers = authToken ? { Authorization: `Bearer ${authToken}` } : {};

      // Make sure ids are arrays
      const toArray = (v) => {
        if (Array.isArray(v)) return v;
        if (v) return [v];
        return [];
      };
      const gIds = toArray(groupIds);
      const mIds = toArray(memberIds);

      try {
        const localDateTime = new Date(`${date}T${time}:00`);
        const sendAt = localDateTime.toISOString();

        // Create schedule record and use server response to update UI
        const postRes = await axios.post(
          `${base}/api/assessment-schedules`,
          {
            assessment_id: this.selectedAssessment?.id,
            date,
            time,
            send_at: sendAt,
            group_ids: gIds,
            member_ids: mIds,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
          },
          { headers }
        );

        const savedSchedule = postRes?.data || null;

        // Queue individual scheduled emails (best-effort). Be defensive about
        // selectedMembers shape (may be ids, objects, or empty) and never throw.
        const safeSelected = Array.isArray(selectedMembers) ? selectedMembers : [];
        const emailPromises = safeSelected.map((member) => {
          try {
            // member may be an object or an id; require an object with email to proceed
            if (!member || typeof member !== 'object' || !member.email)
              return Promise.resolve({ skipped: true });

            const group_id =
              member.group_id ||
              (Array.isArray(member.group_ids) && member.group_ids[0]) ||
              (gIds.length === 1 ? gIds[0] : null);

            return axios.post(
              `${base}/api/schedule-email`,
              {
                recipient_email: member.email,
                subject: 'Assessment Scheduled',
                body: `You have an assessment scheduled: ${this.selectedAssessment?.name || ''}`,
                send_at: sendAt,
                assessment_id: this.selectedAssessment?.id || null,
                member_id: member.id || null,
                group_id,
              },
              { headers }
            );
          } catch (error) {
            // Never rethrow from the map callback — return a resolved failure marker
            return Promise.resolve({ error: error, member });
          }
        });

        const settled = await Promise.allSettled(emailPromises);
        const failed = settled
          .filter((r) => r.status === 'fulfilled' && r.value && r.value.error)
          .map((r) => r.value && r.value.member?.email)
          .filter(Boolean);

        const msg = failed.length
          ? `Assessment scheduled - ${failed.length} email(s) failed`
          : 'Assessment scheduled';
        this._showToast('success', 'Scheduled', msg);

        // Update the assessments array using the returned schedule payload
        try {
          if (this.selectedAssessment && this.selectedAssessment.id) {
            const updated = { ...this.selectedAssessment, schedule: savedSchedule };
            this.assessments = this.assessments.map((a) => (a.id === updated.id ? updated : a));
          } else {
            // Fallback: reload everything
            await this.initializeComponent();
          }
        } catch (err_) {
          console.debug && console.debug('[AssessmentsCard] update-after-schedule failed', err_);
          await this.initializeComponent();
        }

        // Close modal after we've updated state
        this.closeScheduleModal();
      } catch (e) {
        console.debug && console.debug('Failed to schedule assessment', e?.message || e);
        this._showToast('error', 'Failed', 'Could not schedule assessment.');
      } finally {
        this.loading = false;
      }
    },
    goToSummary(item) {
      const orgAssessmentId =
        item.organization_assessment_id || (item.organization_assessment && item.organization_assessment.id) ||
        (item.schedule && item.schedule.organization_assessment_id) || null;

      const params = { assessmentId: item.id };
      if (orgAssessmentId) params.organizationAssessmentId = orgAssessmentId;

      this.$router.push({ name: 'AssessmentSummary', params });
    },

    // --- Pagination ---
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

    // --- Formatters & Utilities ---
    formatLocalDateTime(dateStr, timeStr) {
      try {
        const date = this._parseDateTime(dateStr, timeStr);
        if (!date) return `${dateStr || ''} ${timeStr || ''}`.trim();

        const options = {
          day: '2-digit',
          month: 'short',
          year: 'numeric',
          hour: 'numeric',
          minute: '2-digit',
          hour12: true,
        };
        const formatter = new Intl.DateTimeFormat('en-US', options);
        return formatter.format(date).replace(',', '');
      } catch (e) {
        console.debug && console.debug('Failed to format date/time', e);
        return `${dateStr || ''} ${timeStr || ''}`.trim();
      }
    },
    _parseDateTime(dateStr, timeStr) {
      if (!dateStr) return null;
      const dateTimeString = `${String(dateStr).trim()} ${String(timeStr || '00:00:00').trim()}`;
      const date = new Date(dateTimeString);
      return Number.isNaN(date.getTime()) ? null : date;
    },
    _showToast(severity, summary, detail, life = 3500) {
      this.toast.add({ severity, summary, detail, life });
    },
  },
};
</script>

<style scoped>
.assessments-card {
  width: 100%;
  min-width: 0;
  background: #fff;
  border-radius: 24px;
  border: 1px solid #ebebeb;
  box-shadow: 0 2px 16px 0 rgba(33, 150, 243, 0.04);
  padding: 0;
  display: flex;
  flex-direction: column;
  position: relative;
}

.assessments-header-row {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  padding: 24px;
  border-top-left-radius: 24px;
  border-top-right-radius: 24px;
}

.table-container {
  width: 100%;
  overflow-x: auto;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th,
.table td {
  padding: 12px 24px;
  text-align: left;
  border-bottom: 1px solid #ebebeb;
}

.table th {
  background: #f9f9f9;
  font-weight: 600;
}

.assessment-link {
  background: none;
  border: none;
  color: #0074c2;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  text-align: left;
}
.assessment-link:hover {
  text-decoration: underline;
}

.schedule-btn {
  background: #f5f5f5;
  border-radius: 999px;
  padding: 8px 16px;
  min-width: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: none;
  cursor: pointer;
}
.schedule-btn:hover {
  background: #e6f0fa;
}

.btn-icon {
  width: 18px;
  height: 18px;
  vertical-align: middle;
}

.assessments-header-actions .btn-icon {
  margin-right: 8px;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;

  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

@media (max-width: 768px) {
  .assessments-header-row {
    padding: 16px;
  }
  .table th,
  .table td {
    padding: 8px 12px;
  }
}
</style>
