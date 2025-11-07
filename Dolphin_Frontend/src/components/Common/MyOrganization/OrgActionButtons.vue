<template>
  <div class="table-header-bar">
    <div class="my-org-action-buttons">
      <button
        class="my-org-secondary"
        @click="$router.push({ name: 'MemberListing' })"
      >
        Members Listing
      </button>

      <button class="my-org-primary" @click="openAddGroupModal">
        <img
          src="@/assets/images/Add.svg"
          alt="Add"
          style="
            width: 18px;
            height: 18px;
            margin-right: 6px;
            vertical-align: middle;
          "
        />
        Add New Group
      </button>
      <button class="my-org-primary" @click="openAddMemberModal">
        <img
          src="@/assets/images/Add.svg"
          alt="Add"
          style="
            width: 18px;
            height: 18px;
            margin-right: 6px;
            vertical-align: middle;
          "
        />
        Add New Member
      </button>
    </div>
    <!-- Add New Member Modal -->
    <div
      v-if="showAddMemberModal"
      class="modal-overlay"
      @click.self="showAddMemberModal = false"
    >
      <div class="modal-card" style="max-width: 900px">
        <button class="modal-close-btn" @click="showAddMemberModal = false">
          &times;
        </button>
        <div class="modal-title">Add Member to Organization</div>
        <div
          class="modal-desc"
          style="font-size: 1.2rem !important; margin-bottom: 32px !important"
        >
          Select an existing user to add as a member to your organization.
        </div>
        <form class="modal-form" @submit.prevent="saveMember">
          <FormRow class="modal-form-row">
            <div class="modal-form-row-div" style="width: 100%">
              <FormLabel
                style="font-size: 1rem !important; margin: 0 0 6px 0 !important"
                >Select Users</FormLabel
              >
              <MultiSelectDropdown
                :options="availableUsersForMember"
                :selectedItems="
                  Array.isArray(newMember.selectedUsers)
                    ? newMember.selectedUsers
                    : []
                "
                @update:selectedItems="newMember.selectedUsers = $event"
                placeholder="Select users to add as members"
                :enableSelectAll="true"
                icon="fas fa-users"
              />
              <FormLabel v-if="errors.user_ids" class="error-message1">
                {{ errors.user_ids[0] }}
              </FormLabel>
            </div>
          </FormRow>
          <div class="modal-form-actions">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i>
              Add Members
            </button>
            <button
              type="button"
              class="org-edit-cancel"
              @click="showAddMemberModal = false"
            >
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- Add New Group Modal -->
    <div
      v-if="showAddGroupModal"
      class="modal-overlay"
      @click.self="showAddGroupModal = false"
    >
      <div class="modal-card" style="max-width: 900px">
        <button class="modal-close-btn" @click="showAddGroupModal = false">
          &times;
        </button>
        <div class="modal-title">Add New Group</div>
        <div
          class="modal-desc"
          style="font-size: 1.2rem !important ; margin-bottom: 32px !important"
        >
          Create a new group for your organization.
        </div>
        <form class="modal-form" @submit.prevent="saveGroup">
          <FormRow
            class="modal-form-row"
            style="
              margin-bottom: 0 !important;
              display: flex;
              gap: 18px;
              align-items: flex-start;
              flex-direction: row;
            "
          >
            <div class="modal-form-row-div" style="flex: 1; min-width: 0">
              <FormLabel
                style="font-size: 1rem !important; margin: 0 0 6px 0 !important"
                >Group Name</FormLabel
              >
              <FormInput
                v-model="newGroup.name"
                icon="fas fa-users"
                type="text"
                placeholder="Enter group name"
                required
              />
              <FormLabel v-if="errors.name" class="error-message1">
                {{ errors.name[0] }}
              </FormLabel>
            </div>

            <div class="modal-form-row-div" style="flex: 1; min-width: 0">
              <FormLabel
                style="font-size: 1rem !important; margin: 0 0 6px 0 !important"
                >Members</FormLabel
              >
              <MultiSelectDropdown
                :options="availableMembers"
                :selectedItems="
                  Array.isArray(newGroup.members) ? newGroup.members : []
                "
                @update:selectedItems="newGroup.members = $event"
                placeholder="Select members"
                :enableSelectAll="true"
              />
              <FormLabel v-if="errors.member_ids" class="error-message1">
                {{ errors.member_ids[0] }}
              </FormLabel>
            </div>
          </FormRow>
          <div class="modal-form-actions">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i>
              Save
            </button>
            <button
              type="button"
              class="org-edit-cancel"
              @click="showAddGroupModal = false"
            >
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import FormDropdown from "@/components/Common/Common_UI/Form/FormDropdown.vue";
import FormInput from "@/components/Common/Common_UI/Form/FormInput.vue";
import FormLabel from "@/components/Common/Common_UI/Form/FormLabel.vue";
import FormRow from "@/components/Common/Common_UI/Form/FormRow.vue";
import MultiSelectDropdown from "@/components/Common/Common_UI/Form/MultiSelectDropdown.vue";
import storage from "@/services/storage";
import axios from "axios";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
export default {
  name: "OrgActionButtons",
  components: {
    FormInput,
    FormLabel,
    FormDropdown,
    MultiSelectDropdown,
    FormRow,
    Toast,
  },
  data() {
    return {
      showAddMemberModal: false,
      showAddGroupModal: false,
      newMember: {
        selectedUsers: [],
      },
      newGroup: {
        name: "",
        members: [],
      },
      roles: [],
      groups: [],
      availableMembers: [],
      availableUsersForMember: [],
      toast: null,
      errors: {},
    };
  },
  async mounted() {
    this.toast = useToast();
  },
  methods: {
    async openAddMemberModal() {
      // Fetch available users with 'user' or 'salesperson' roles who are not already members
      try {
        const authToken = storage.get("authToken");
        const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;
        const res = await axios.get(
          `${API_BASE_URL}/api/organization/members/available`,
          {
            headers: { Authorization: `Bearer ${authToken}` },
          }
        );

        // Format users for MultiSelectDropdown (expecting array with id and name)
        this.availableUsersForMember = res.data.map((user) => ({
          id: user.id,
          name: `${user.first_name} ${user.last_name} (${user.email})`,
        }));
      } catch (e) {
        console.error("Failed to fetch available users:", e);
        this.availableUsersForMember = [];
        this.$toast.add({
          severity: "error",
          summary: "Error",
          detail: "Failed to load available users",
          life: 3000,
        });
      }

      // Clear any previous errors
      this.errors = {};
      this.showAddMemberModal = true;
    },

    async openAddGroupModal() {
      // Fetch members from organization_member table for this organization
      this.availableMembers = [];
      const authToken = storage.get("authToken");
      const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;

      try {
        const res = await axios.get(
          `${API_BASE_URL}/api/organization/members/for-groups`,
          {
            headers: { Authorization: `Bearer ${authToken}` },
          }
        );

        const userData = res.data;
        if (Array.isArray(userData)) {
          this.availableMembers = userData.map((u) => ({
            ...u,
            id: u.id,
            name: u.name || `${u.first_name || ""} ${u.last_name || ""}`.trim(),
          }));
        } else {
          this.availableMembers = [];
        }
      } catch (error) {
        console.error("Failed to fetch organization members:", error);
        this.availableMembers = [];
        this.$toast.add({
          severity: "error",
          summary: "Error",
          detail: "Failed to load organization members",
          life: 3000,
        });
      }

      // Clear any previous errors
      this.errors = {};
      this.showAddGroupModal = true;
    },

    async saveMember() {
      try {
        // Validate that at least one user is selected
        if (
          !Array.isArray(this.newMember.selectedUsers) ||
          this.newMember.selectedUsers.length === 0
        ) {
          this.$toast.add({
            severity: "warn",
            summary: "Warning",
            detail: "Please select at least one user to add as member.",
            life: 4000,
          });
          return;
        }

        const authToken = storage.get("authToken");
        const headers = {};
        if (authToken) headers["Authorization"] = `Bearer ${authToken}`;

        // Add each selected user as a member
        const userIds = this.newMember.selectedUsers.map(
          (u) => u.id || u.value || u
        );
        let successCount = 0;
        let failedCount = 0;

        for (const userId of userIds) {
          try {
            await axios.post(
              process.env.VUE_APP_API_BASE_URL +
                "/api/organization/members/add",
              { user_id: userId },
              { headers }
            );
            successCount++;
          } catch (err) {
            console.error(`Failed to add user ${userId}:`, err);
            failedCount++;
          }
        }

        // Success - clear errors and close modal
        this.errors = {};
        this.showAddMemberModal = false;
        this.availableUsersForMember = [];
        this.newMember = {
          selectedUsers: [],
        };

        // Show appropriate success message
        if (successCount > 0 && failedCount === 0) {
          this.$toast.add({
            severity: "success",
            summary: "Success",
            detail: `${successCount} member${
              successCount > 1 ? "s" : ""
            } added successfully!`,
            life: 3000,
          });
        } else if (successCount > 0 && failedCount > 0) {
          this.$toast.add({
            severity: "warn",
            summary: "Partial Success",
            detail: `${successCount} member${
              successCount > 1 ? "s" : ""
            } added, ${failedCount} failed.`,
            life: 4000,
          });
        } else {
          this.$toast.add({
            severity: "error",
            summary: "Error",
            detail: "Failed to add members.",
            life: 4000,
          });
        }

        this.$emit("member-added");
      } catch (e) {
        // Handle validation errors - don't close modal
        if (
          e.response &&
          e.response.status === 422 &&
          e.response.data &&
          e.response.data.errors
        ) {
          this.errors = e.response.data.errors;
          this.$toast.add({
            severity: "error",
            summary: "Validation Error",
            detail: "Please fix the errors below and try again.",
            life: 3000,
          });
          return; // Don't close modal, let user fix errors
        }

        // Handle other types of errors
        let msg = "Failed to add members.";
        if (e.response && e.response.data && e.response.data.message) {
          msg = e.response.data.message;
        } else if (
          e.response &&
          e.response.data &&
          typeof e.response.data === "string"
        ) {
          msg = e.response.data;
        } else {
          console.error(e);
        }
        this.$toast.add({
          severity: "error",
          summary: "Error",
          detail: msg,
          life: 4000,
        });

        // Close modal for non-validation errors
        this.showAddMemberModal = false;
        this.availableUsersForMember = [];
        this.newMember = {
          selectedUsers: [],
        };
      }
    },

    async saveGroup() {
      try {
        const payload = {
          name: this.newGroup.name,
          // Send as user_ids for new system, backend also accepts member_ids for compatibility
          user_ids: Array.isArray(this.newGroup.members)
            ? this.newGroup.members.map((m) => m.id || m.value || m)
            : [],
        };
        const authToken = storage.get("authToken");
        const headers = {};
        if (authToken) headers["Authorization"] = `Bearer ${authToken}`;
        await axios.post(
          process.env.VUE_APP_API_BASE_URL + "/api/groups",
          payload,
          { headers }
        );

        // Success - clear errors and close modal
        this.errors = {};
        this.showAddGroupModal = false;
        this.availableMembers = [];
        this.newGroup = { name: "", members: [] };

        this.toast.add({
          severity: "success",
          summary: "Success",
          detail: "Group added successfully!",
          life: 3000,
        });
        this.$emit("group-added");
      } catch (e) {
        // Handle validation errors - don't close modal
        if (
          e.response &&
          e.response.status === 422 &&
          e.response.data &&
          e.response.data.errors
        ) {
          this.errors = e.response.data.errors;
          this.toast.add({
            severity: "error",
            summary: "Validation Error",
            detail: "Please fix the errors below and try again.",
            life: 3000,
          });
          return; // Don't close modal, let user fix errors
        }

        // Handle other types of errors
        let msg = "Failed to add group.";
        if (e.response && e.response.data && e.response.data.message) {
          msg = e.response.data.message;
        } else if (
          e.response &&
          e.response.data &&
          typeof e.response.data === "string"
        ) {
          msg = e.response.data;
        } else {
          console.error("Failed to add group:", e);
        }
        this.toast.add({
          severity: "error",
          summary: "Error",
          detail: msg,
          life: 4000,
        });

        // Close modal for non-validation errors
        this.showAddGroupModal = false;
        this.availableMembers = [];
        this.newGroup = { name: "", members: [] };
      }
    },
  },
};
</script>

<style scoped>
@import "@/assets/modelcssnotificationandassesment.css";

/* Modal form customization */
.modal-form .form-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;
  margin-bottom: 18px;
}

@media (max-width: 600px) {
  .modal-form-row {
    flex-direction: column !important;
    gap: 12px;
  }
  .modal-form-row-div {
    flex: 1 !important;
    width: 100% !important;
    min-width: 0;
  }
}

.modal-form .form-label {
  font-size: 14px;
  font-weight: 500;
  color: var(--text);
  text-align: left;
  margin-bottom: 6px;
}

/* Ensure form components work well in modal */
.modal-form .form-box {
  position: relative;
}

.modal-form .form-input.with-icon {
  padding-left: 40px;
}

.modal-form .form-input-icon {
  color: var(--muted);
  font-size: 16px;
}

.modal-form .form-dropdown-chevron {
  color: var(--muted);
}

.my-org-action-buttons {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  flex-wrap: wrap;
}

/* Small screens: column */
@media (max-width: 600px) {
  .my-org-action-buttons {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: stretch;
  }
}

.my-org-primary,
.my-org-secondary {
  border-radius: 29.01px;
  font-family: "Helvetica Neue LT Std", Helvetica, Arial, sans-serif;
  font-weight: 500;
  font-size: 15px;

  padding: 8px 24px 8px 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-right: 0;
  margin-top: 0;
  box-shadow: none;

  cursor: pointer;
  transition: background 0.2s, color 0.2s;
  white-space: nowrap;
  min-width: 0;
  max-width: none;
  overflow: visible;
  border: 1px solid #e6e6e6;
}
.my-org-primary {
  background: #0164a5;
  color: #fff;
}
.my-org-primary:hover {
  background: #005fa3;
  color: #fff;
}
.my-org-secondary {
  background: #f5f5f5;
  color: #000000;
}

.my-org-action-buttons .my-org-secondary:nth-child(2) {
  background: #0164a5 !important;
  color: #fff !important;
}
.my-org-action-buttons .my-org-secondary:nth-child(2):hover {
  background: #005fa3 !important;
  color: #fff !important;
}

.btn {
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

.btn-primary {
  background: #0074c2;
  color: #fff;
}

.btn-primary:hover {
  background: #005fa3;
}

.org-edit-cancel {
  background: #f0f0f0;
  color: #222;
  border: none;
  padding: 10px 24px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
}

.org-edit-cancel:hover {
  background: #e0e0e0;
}

.form-box {
  padding: 0 !important;
}

.error-message1 {
  color: red !important;
  font-size: 0.85rem;
  margin-top: 4px;
  display: block;
  font-weight: 400;
}
</style>
