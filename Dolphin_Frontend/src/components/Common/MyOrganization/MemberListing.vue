<template>
  <ConfirmDialog />
  <Toast />
  <MainLayout>
    <div class="page">
      <div class="table-outer">
        <template v-if="!showMemberModal">
          <div class="table-card">
            <div class="table-search-bar">
              <input
                class="org-search"
                placeholder="Search Member..."
                v-model="searchQuery"
                @input="onSearch"
              />
            </div>
            <div class="table-container">
              <div class="table-scroll">
                <table class="table">
                  <TableHeader
                    :columns="[
                      { label: 'Name', key: 'name', minWidth: '200px' },
                      {
                        label: 'Email',
                        key: 'email',
                        minWidth: '200px',
                      },
                      {
                        label: 'Phone Number',
                        key: 'phone',
                        minWidth: '150px',
                      },
                      { label: 'Role', key: 'role', minWidth: '150px' },
                      {
                        label: 'Actions',
                        key: 'actions',
                        minWidth: '100px',
                      },
                    ]"
                    @sort="sortBy"
                  />
                  <tbody>
                    <tr v-if="loading">
                      <td colspan="5" class="no-data">Loading members...</td>
                    </tr>
                    <tr v-else-if="paginatedMembers.length === 0">
                      <td colspan="5" class="no-data">No members found.</td>
                    </tr>
                    <tr
                      v-else
                      v-for="member in paginatedMembers"
                      :key="member.id"
                    >
                      <td>{{ member.first_name }} {{ member.last_name }}</td>
                      <td>{{ member.email }}</td>
                      <td>{{ member.phone }}</td>
                      <td>
                        <span>
                          {{ formatMemberRoles(member) }}
                        </span>
                      </td>
                      <td>
                        <button
                          class="btn-view"
                          @click="openMemberModal(member)"
                        >
                          <img
                            src="@/assets/images/Notes.svg"
                            alt="View"
                            class="btn-view-icon"
                          />
                          View
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <Pagination
            :pageSize="pageSize"
            :pageSizes="[10, 25, 100]"
            :showPageDropdown="showPageDropdown"
            :currentPage="currentPage"
            :totalPages="totalPages"
            :paginationPages="paginationPages"
            @goToPage="goToPage"
            @selectPageSize="selectPageSize"
            @togglePageDropdown="showPageDropdown = !showPageDropdown"
          />
        </template>

        <!-- Member Details Modal -->
        <div
          v-if="showMemberModal"
          class="modal-overlay"
          @click.self="closeMemberModal"
        >
          <div class="modal-card" style="max-width: 800px; width: 90%">
            <button class="modal-close-btn" @click="closeMemberModal">
              &times;
            </button>
            <div class="modal-title">Member Details</div>
            <div class="modal-desc">Details for the selected member.</div>

            <div class="profile-card">
              <div class="profile-header">
                <div class="profile-title">
                  <i class="fas fa-user-circle profile-avatar"></i>
                  <span>Profile</span>
                </div>
              </div>

              <div class="profile-info-table">
                <div class="profile-info-row">
                  <div class="profile-label">First Name</div>
                  <div class="profile-value">
                    {{ selectedMemberEdit.first_name || "Not provided" }}
                  </div>
                </div>
                <div class="profile-info-row">
                  <div class="profile-label">Last Name</div>
                  <div class="profile-value">
                    {{ selectedMemberEdit.last_name || "Not provided" }}
                  </div>
                </div>
                <div class="profile-info-row">
                  <div class="profile-label">Email</div>
                  <div class="profile-value">
                    {{ selectedMemberEdit.email }}
                  </div>
                </div>
                <div class="profile-info-row">
                  <div class="profile-label">Role</div>
                  <div class="profile-value">
                    <span
                      v-if="
                        selectedMemberEdit.memberRoles &&
                        selectedMemberEdit.memberRoles.length > 0
                      "
                    >
                      {{
                        selectedMemberEdit.memberRoles
                          .map((role) => role.name)
                          .join(", ")
                      }}
                    </span>
                    <span v-else>No roles assigned</span>
                  </div>
                </div>

                <div class="profile-info-row">
                  <div class="profile-label">Phone</div>
                  <div class="profile-value">
                    {{ selectedMemberEdit.phone || "Not provided" }}
                  </div>
                </div>
                <div class="profile-info-row">
                  <div class="profile-label">Groups</div>
                  <div class="profile-value">
                    <span
                      v-if="
                        (selectedMemberEdit.groups &&
                          selectedMemberEdit.groups.length > 0) ||
                        (selectedMemberEdit.group_ids &&
                          selectedMemberEdit.group_ids.length > 0)
                      "
                    >
                      {{ formatMemberGroups(selectedMemberEdit) }}
                    </span>
                    <span v-else>No groups assigned</span>
                  </div>
                </div>
                <div class="profile-info-row">
                  <div class="profile-label">Member ID</div>
                  <div class="profile-value">{{ selectedMemberEdit.id }}</div>
                </div>
                <div class="profile-info-row"></div>

                <div class="profile-actions">
                  <button
                  class="btn btn-danger"
                  @click="deleteMember(selectedMemberEdit)"
                  title="Remove member from organization"
                  >
                  <i class="fas fa-user-minus"></i>
                  Remove from Organization
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script>
import TableHeader from "@/components/Common/Common_UI/TableHeader.vue";
import MainLayout from "@/components/layout/MainLayout.vue";
import Pagination from "@/components/layout/Pagination.vue";
import storage from "@/services/storage";
import axios from "axios";
import Toast from "primevue/toast";
import { useConfirm } from "primevue/useconfirm";

export default {
  name: "MemberListing",
  components: {
    MainLayout,
    Pagination,
    TableHeader,
    Toast,
  },
  setup() {
    const confirm = useConfirm();
    return { confirm };
  },
  data() {
    return {
      currentPage: 1,
      pageSize: 10,
      searchQuery: "",
      showPageDropdown: false,
      sortKey: "",
      sortAsc: true,
      members: [],
      filteredMembers: [],
      loading: true,
      showMemberModal: false,
      selectedMemberEdit: {},
      groupsForSelect: [],
      groupsForSelectMap: {},
    };
  },
  computed: {
    totalPages() {
      return Math.ceil(this.filteredMembers.length / this.pageSize) || 1;
    },
    paginatedMembers() {
      const sorted = [...this.filteredMembers];
      if (this.sortKey) {
        sorted.sort((a, b) => {
          const aVal = a[this.sortKey] || "";
          const bVal = b[this.sortKey] || "";
          if (aVal < bVal) return this.sortAsc ? -1 : 1;
          if (aVal > bVal) return this.sortAsc ? 1 : -1;
          return 0;
        });
      }
      const start = (this.currentPage - 1) * this.pageSize;
      return sorted.slice(start, start + this.pageSize);
    },
    paginationPages() {
      const total = this.totalPages;
      if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
      }
      const pages = [1];
      if (this.currentPage > 4) pages.push("...");
      const start = Math.max(2, this.currentPage - 1);
      const end = Math.min(total - 1, this.currentPage + 1);
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }
      if (this.currentPage < total - 3) pages.push("...");
      pages.push(total);
      return pages;
    },
  },
  methods: {
    normalizeMember(member) {
      if (!member) return {};
      const normalized = { ...member };
      normalized.memberRoles = Array.isArray(normalized.memberRoles)
        ? normalized.memberRoles
        : [];

      // Convert memberRoles to member_role_ids for editing
      if (normalized.memberRoles.length > 0) {
        normalized.member_role_ids = normalized.memberRoles.map((role) => ({
          id: role.id,
          name: role.name,
        }));
      } else {
        normalized.member_role_ids = [];
      }

      return normalized;
    },

    async openMemberModal(member) {
      // Always fetch full member data from the API by ID so we use the DB's created_at
      let memberId = null;
      if (typeof member === "string" || typeof member === "number") {
        memberId = member;
      }

      if (memberId == null && member?.id) {
        memberId = member.id;
      }

      if (memberId) {
        await this.fetchMemberById(memberId);
      } else {
        // No valid ID provided — fallback to whatever was passed in
        this.selectedMemberEdit = this.normalizeMember(member);
      }

      // Update URL to include member ID for direct linking
      if (this.selectedMemberEdit?.id) {
        this.$router.push({
          path: this.$route.path,
          query: {
            ...this.$route.query,
            member_id: this.selectedMemberEdit.id,
          },
        });
      }

      this.showMemberModal = true;
    },

    async fetchMemberById(memberId) {
      // NOTE: Deprecated /api/members endpoint removed. Using cached member data instead.
      try {
        const existingMember = this.members.find(
          (m) => m.id === Number.parseInt(memberId)
        );
        if (existingMember) {
          this.selectedMemberEdit = this.normalizeMember(existingMember);
        } else {
          this.$toast.add({
            severity: "error",
            summary: "Error",
            detail: "Member not found.",
            life: 3000,
          });
        }
      } catch (error) {
        console.error("Failed to fetch member details:", error);
        this.$toast.add({
          severity: "error",
          summary: "Error",
          detail: "Could not load member details.",
          life: 3000,
        });
      }
    },

    formatMemberRoles(member) {
      if (
        member &&
        Array.isArray(member.memberRoles) &&
        member.memberRoles.length > 0
      ) {
        return member.memberRoles.map((r) => r.name).join(", ");
      }
      return member.member_role || "No Role";
    },

    formatMemberGroups(member) {
      // If we have full group objects with names, use them
      if (member && Array.isArray(member.groups) && member.groups.length > 0) {
        return member.groups.map((group) => group.name).join(", ");
      }

      // If we only have group IDs, look them up in our groups cache
      if (
        member &&
        Array.isArray(member.group_ids) &&
        member.group_ids.length > 0
      ) {
        const groupNames = member.group_ids
          .map((groupId) => {
            const group = this.groupsForSelectMap[groupId];
            return group ? group.name : `Group ${groupId}`;
          })
          .filter(Boolean); // Remove any null/undefined names

        return groupNames.length > 0
          ? groupNames.join(", ")
          : "No groups assigned";
      }

      return "No groups assigned";
    },

    formatDate(dateString) {
      if (!dateString) return "Not available";
      try {
        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) return "Invalid date";
        return date.toLocaleDateString("en-US", {
          year: "numeric",
          month: "short",
          day: "numeric",
        });
      } catch (error) {
        console.warn("Error formatting date:", error);
        return "Invalid date";
      }
    },

    closeMemberModal() {
      this.showMemberModal = false;
      // Remove member_id from URL when closing modal
      const query = { ...this.$route.query };
      delete query.member_id;
      this.$router.push({ path: this.$route.path, query });
    },

    async deleteMember(member) {
      const memberDisplay =
        `${member.first_name} ${member.last_name}`.trim() || member.email;
      this.confirm.require({
        message: `Are you sure you want to delete ${memberDisplay}?`,
        header: "Confirm Delete",
        icon: "pi pi-trash",
        accept: async () => {
          try {
            // NOTE: /api/members DELETE endpoint removed (used non-existent members table)
            // Use /api/organization/members/remove endpoint instead
            const authToken = storage.get("authToken");
            const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;
            await axios.post(
              `${API_BASE_URL}/api/organization/members/remove`,
              {
                user_id: member.id,
              },
              {
                headers: { Authorization: `Bearer ${authToken}` },
              }
            );

            this.members = this.members.filter((m) => m.id !== member.id);
            this.onSearch();
            this.showMemberModal = false;
            this.$toast.add({
              severity: "info",
              summary: "Deleted",
              detail: "Member has been removed from the organization.",
              life: 3000,
            });
          } catch (e) {
            console.error("Failed to remove member", e);
            this.$toast.add({
              severity: "error",
              summary: "Remove Failed",
              detail: "Failed to remove member from organization.",
              sticky: true,
            });
          }
        },
      });
    },

    onSearch() {
      const query = this.searchQuery.trim().toLowerCase();
      if (query) {
        this.filteredMembers = this.members.filter((m) =>
          Object.values(m).some((val) =>
            String(val).toLowerCase().includes(query)
          )
        );
      } else {
        this.filteredMembers = [...this.members];
      }
      this.currentPage = 1;
    },

    goToPage(page) {
      if (typeof page === "number" && page >= 1 && page <= this.totalPages) {
        this.currentPage = page;
      }
    },

    selectPageSize(size) {
      this.pageSize = size;
      this.currentPage = 1;
      this.showPageDropdown = false;
    },

    sortBy(key) {
      if (this.sortKey === key) {
        this.sortAsc = !this.sortAsc;
      } else {
        this.sortKey = key;
        this.sortAsc = true;
      }
    },

    async fetchInitialData() {
      this.loading = true;
      try {
        const authToken = storage.get("authToken");
        const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;

        const [membersRes, groupsRes] = await Promise.all([
          // Use new organization members endpoint
          axios.get(`${API_BASE_URL}/api/organization/members`, {
            headers: { Authorization: `Bearer ${authToken}` },
          }),
          axios.get(`${API_BASE_URL}/api/groups`, {
            headers: { Authorization: `Bearer ${authToken}` },
          }),
        ]);

        // Process groups data
        const groupsData = groupsRes.data?.data || groupsRes.data || [];
        if (Array.isArray(groupsData) && groupsData.length) {
          this.groupsForSelect = groupsData.map((g) => ({
            id: g.id,
            name: g.name,
          }));
          this.groupsForSelectMap = this.groupsForSelect.reduce(
            (map, group) => {
              map[group.id] = group;
              return map;
            },
            {}
          );
        }

        // Process members data - now these are users with groups
        const membersData = membersRes.data?.data || [];
        this.members = membersData.map((user) => {
          // Map user data to match old member structure for compatibility
          const member = {
            id: user.id,
            first_name: user.first_name,
            last_name: user.last_name,
            email: user.email,
            phone: user.phone,
            organization_id: user.organization_id,
            // Groups with pivot role
            groups: user.groups || [],
            // Extract roles from group pivot data
            memberRoles:
              user.groups && user.groups.length > 0
                ? user.groups.map((g) => ({
                    id: g.id,
                    name: g.pivot?.role || "member",
                  }))
                : [],
          };
          return this.normalizeMember(member);
        });
        this.filteredMembers = [...this.members];
      } catch (error) {
        console.error("Failed to fetch initial data:", error);
        this.$toast.add({
          severity: "error",
          summary: "Failed to load data",
          detail: "Could not fetch members from the server.",
          life: 5000,
        });
      } finally {
        this.loading = false;
      }
    },
  },
  async mounted() {
    await this.fetchInitialData();
    const memberIdFromQuery = this.$route.query.member_id;
    if (memberIdFromQuery) {
      const member = this.members.find(
        (m) => m.id === Number.parseInt(memberIdFromQuery)
      );
      if (member) {
        await this.openMemberModal(member);
      } else {
        // If member not found in initial list, try to fetch by ID
        console.warn(
          `Member with ID ${memberIdFromQuery} not found in the initial list. Attempting to fetch...`
        );
        await this.openMemberModal(memberIdFromQuery);
      }
    }
  },
};
</script>

<style>
@import "@/assets/global.css";
@import "@/assets/modelcssnotificationandassesment.css";

.org-search {
  width: 260px;
  padding: 8px 24px 8px 32px;
  border-radius: 12px;
  border: none;
  background: #f8f8f8;
  font-size: 14px;
  outline: none;
  background-image: url("@/assets/images/Search.svg");
  background-repeat: no-repeat;
  background-position: 8px center;
  background-size: 16px 16px;
  margin-left: 0;
  margin-right: auto;
}
.org-search::placeholder {
  margin-left: 4px;
}

.member-profile-card .profile-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 28px 32px 0 32px;
}
@media (max-width: 600px) {
  .member-profile-card .profile-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 28px 32px 0 32px;
    flex-direction: column;
  }
}
.member-profile-card .profile-title {
  display: flex;
  align-items: center;
  gap: 14px;
  font-size: 1.5rem;
  font-weight: 600;
  color: #0074c2;
}
.member-profile-card .profile-avatar {
  font-size: 2.2rem;
  color: #0074c2;
}
.member-profile-card .profile-info-table {
  padding: 18px 32px 0 32px;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.member-profile-card .profile-info-row {
  display: flex;
  border-bottom: 1px solid #f0f0f0;
  padding: 14px 0;
  align-items: center;
}
.member-profile-card .profile-label {
  min-width: 160px;
  color: #888;
  font-weight: 400;
  font-size: 1rem;
}
@media (max-width: 600px) {
  .member-profile-card .profile-label {
    min-width: 70px !important;
    font-size: 0.9rem;
  }
}
.member-profile-card .profile-value {
  color: #222;
  font-weight: 500;
  font-size: 1rem;
  word-break: break-word;
}
.member-profile-card .profile-edit-input {
  background: #fff;
  border: 1.5px solid #e0e0e0;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 15px;
  color: #222;
  outline: none;
  transition: border 0.2s;
  margin-bottom: 0;
  margin-right: 0;
}
.member-profile-card .profile-actions {
  display: flex;
  justify-content: flex-end;
  padding: 18px 32px 32px 32px;
}
@media (max-width: 600px) {
  .member-profile-card .profile-actions {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
}
.member-profile-card .btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 1rem;
  font-weight: 500;
  border-radius: 8px;
  border: none;
  padding: 10px 24px;
  cursor: pointer;
  transition: background 0.2s;
}
.member-profile-card .btn-primary {
  background: #0074c2;
  color: #fff;
}
.member-profile-card .btn-primary:hover {
  background: #005fa3;
}
.member-profile-card .btn-danger {
  background: #e74c3c;
  color: #fff;
}
.member-profile-card .btn-danger:hover {
  background: #c0392b;
}
.member-profile-card .btn-secondary {
  background: #f0f0f0;
  color: #222;
}
.member-profile-card .btn-secondary:hover {
  background: #e0e0e0;
}

.profile-outer {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-sizing: border-box;
  gap: 40px;
}

/* Profile Card Styles */
.profile-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.profile-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  background: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
}

.profile-title {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 1.25rem;
  font-weight: 600;
  color: #333;
}

.profile-avatar {
  font-size: 1.5rem;
  color: #0074c2;
}

.profile-info-table {
  padding: 0;
}

.profile-info-row {
  display: flex;
  border-bottom: 1px solid #e9ecef;
}

.profile-info-row:last-child {
  border-bottom: none;
}

.profile-label {
  flex: 0 0 150px;
  padding: 16px 20px;
  background: #f8f9fa;
  font-weight: 600;
  color: #495057;
  border-right: 1px solid #e9ecef;
}

.profile-value {
  flex: 1;
  padding: 16px 20px;
  color: #333;
  background: white;
}

.profile-actions {
  padding: 20px;
  background: #f8f9fa;
  border-top: 1px solid #e9ecef;
  display: flex;
  justify-content: flex-end;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border: none;
  border-radius: 24px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
}

.btn-primary {
  background: #0074c2;
  color: white;
}

.btn-primary:hover {
  background: #005fa3;
}

.btn-danger {
  background: #dc3545;
  color: white;
}

.btn-danger:hover {
  background: #c82333;
}
</style>
