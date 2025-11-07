<template>
  <div class="modal-overlay">
    <div class="modal-card">
      <button class="modal-close" @click="$emit('close')">&times;</button>
      <div class="modal-title">Create Assessment</div>

      <form class="modal-form" @submit.prevent="handleSubmit">
        <div class="modal-form-row">
          <div
            class="modal-form-group"
            style="padding: 0; background: none; border-radius: 0; height: auto"
          >
            <input
              v-model="assessment.name"
              type="text"
              placeholder="Assessment Name"
              required
              style="
                width: 100%;
                background: #f6f6f6;
                border-radius: 9px;
                border: 1.5px solid #e0e0e0;
                font-size: 20px;
                padding: 16px 20px;
                box-sizing: border-box;
                font-weight: 500;
                color: #222;
              "
            />
          </div>
        </div>

        <div class="modal-form-actions">
          <button type="submit" class="modal-save-btn" :disabled="isSubmitting">
            {{ isSubmitting ? "Creating..." : "Create" }}
          </button>
          <button
            type="button"
            class="org-edit-cancel"
            @click="$emit('close')"
            style="margin-left: 12px"
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import storage from "@/services/storage";
import axios from "axios";

export default {
  name: "CreateAssessmentModal",
  emits: ["close", "assessment-created", "validation-error", "error"],
  data() {
    return {
      assessment: {
        name: "",
      },
      isSubmitting: false,
    };
  },
  methods: {
    resetForm() {
      this.assessment = { name: "" };
      this.isSubmitting = false;
    },
    async handleSubmit() {
      if (!this.assessment.name || this.assessment.name.trim() === "") {
        this.$emit("validation-error", {
          type: "warn",
          title: "Missing Data",
          message: "Please enter a name for the assessment.",
        });
        return;
      }

      this.isSubmitting = true;
      try {
        const authToken = storage.get("authToken");
        const res = await axios.post(
          process.env.VUE_APP_API_BASE_URL + "/api/assessments",
          { name: this.assessment.name },
          { headers: { Authorization: `Bearer ${authToken}` } }
        );

        if (res.data && res.data.assessment) {
          this.$emit("assessment-created", res.data.assessment);
          this.resetForm();
          this.$emit("close");
        } else {
          this.$emit("error", {
            type: "error",
            title: "Error",
            message: "Failed to create assessment. Please try again.",
          });
        }
      } catch (e) {
        console.error("Error creating assessment", e);
        this.$emit("error", {
          type: "error",
          title: "Error",
          message:
            (e.response && e.response.data && e.response.data.message) ||
            "Failed to create assessment. Please try again.",
        });
      } finally {
        this.isSubmitting = false;
      }
    },
  },
  mounted() {
    this.resetForm();
  },
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  background: rgba(0, 0, 0, 0.4);
}

.modal-card {
  width: 520px;
  max-width: 95%;
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-sizing: border-box;
  position: relative;
}

.modal-close {
  position: absolute;
  right: 12px;
  top: 8px;
  border: none;
  background: transparent;
  font-size: 24px;
  cursor: pointer;
}

.modal-title {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 12px;
}

.modal-form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.modal-save-btn {
  background: #0074c2;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 10px 18px;
  cursor: pointer;
}

.org-edit-cancel {
  background: transparent;
  border: none;
  color: #444;
  padding: 10px 18px;
  cursor: pointer;
}
</style>
