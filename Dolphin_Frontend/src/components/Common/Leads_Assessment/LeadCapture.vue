<template>
  <Toast />
  <MainLayout>
    <div class="page">
      <div class="lead-capture-outer">
        <div class="lead-capture-card">
          <h3 class="lead-capture-card-title">Enter Lead Details</h3>
          <form class="lead-capture-form" @submit.prevent="handleSaveLead">
            <FormRow>
              <div>
                <FormLabel>First Name</FormLabel>
                <FormInput
                  v-model="form.firstName"
                  icon="fas fa-user"
                  placeholder="Type here"
                  required
                />
                <FormLabel v-if="errors.firstName" class="error-message">{{
                  errors.firstName[0]
                }}</FormLabel>
              </div>
              <div>
                <FormLabel>Last Name</FormLabel>
                <FormInput
                  v-model="form.lastName"
                  icon="fas fa-user"
                  placeholder="Type here"
                  required
                />
                <FormLabel v-if="errors.lastName" class="error-message">{{
                  errors.lastName[0]
                }}</FormLabel>
              </div>
              <div>
                <FormLabel>Email</FormLabel>
                <FormInput
                  v-model="form.email"
                  icon="fas fa-envelope"
                  type="email"
                  placeholder="abc@gmail.com"
                  required
                />
                <FormLabel v-if="errors.email" class="error-message">{{
                  errors.email[0]
                }}</FormLabel>
              </div>
            </FormRow>
            <FormRow>
              <div>
                <FormLabel>Phone</FormLabel>
                <FormInput
                  v-model="form.phone_number"
                  icon="fas fa-phone"
                  placeholder="Type here"
                  required
                />
                <FormLabel v-if="errors.phone_number" class="error-message">{{
                  errors.phone_number[0]
                }}</FormLabel>
              </div>
              <div>
                <FormLabel>How did you find us?</FormLabel>
                <FormDropdown
                  v-model="form.referral_source_id"
                  icon="fas fa-search"
                  :options="[
                    { value: null, text: 'Select', disabled: true },
                    ...referralSources.map((o) => ({
                      value: o.id,
                      text: o.name,
                    })),
                  ]"
                  required
                />
                <FormLabel
                  v-if="errors.referral_source_id"
                  class="error-message"
                  >{{ errors.referral_source_id[0] }}</FormLabel
                >
              </div>
              <div v-if="isReferralSourceOther">
                <FormLabel>Please specify</FormLabel>
                <FormInput
                  v-model="form.referral_other_text"
                  icon="fas fa-comment"
                  placeholder="Please specify how you found us"
                  :required="isReferralSourceOther"
                />
                <FormLabel
                  v-if="errors.referral_other_text"
                  class="error-message"
                  >{{ errors.referral_other_text[0] }}</FormLabel
                >
              </div>
              <div v-else></div>
            </FormRow>
            <FormRow>
              <div>
                <FormLabel>Organization Name</FormLabel>
                <FormInput
                  v-model="form.organization_name"
                  icon="fas fa-cog"
                  placeholder="Organization Name"
                  required
                />
                <FormLabel
                  v-if="errors.organization_name"
                  class="error-message"
                  >{{ errors.organization_name[0] }}</FormLabel
                >
              </div>
              <div>
                <FormLabel>Organization Size</FormLabel>
                <FormDropdown
                  v-model="form.organization_size"
                  icon="fas fa-users"
                  :options="[
                    { value: null, text: 'Select', disabled: true },
                    ...orgSizeOptions.map((o) => ({ value: o, text: o })),
                  ]"
                  required
                />
                <FormLabel
                  v-if="errors.organization_size"
                  class="error-message"
                  >{{ errors.organization_size[0] }}</FormLabel
                >
              </div>
              <div></div>
            </FormRow>
            <FormRow>
              <div>
                <FormLabel>Address Line 1</FormLabel>
                <FormInput
                  v-model="form.address_line_1"
                  icon="fas fa-map-marker-alt"
                  placeholder="153, Maggie Loop Pottsville"
                  required
                />
                <FormLabel v-if="errors.address_line_1" class="error-message">{{
                  errors.address_line_1[0]
                }}</FormLabel>
              </div>
              <div>
                <FormLabel>Address Line 2</FormLabel>
                <FormInput
                  v-model="form.address_line_2"
                  icon="fas fa-map-marker-alt"
                  placeholder="Apartment, suite, etc. (optional)"
                />
                <FormLabel v-if="errors.address_line_2" class="error-message">{{
                  errors.address_line_2[0]
                }}</FormLabel>
              </div>
              <div></div>
            </FormRow>
            <FormRow>
              <div>
                <FormLabel>Country</FormLabel>
                <FormDropdown
                  v-model="form.country_id"
                  icon="fas fa-globe"
                  @change="onCountryChange"
                  :options="[
                    { value: null, text: 'Select', disabled: true },
                    ...countries.map((c) => ({ value: c.id, text: c.name })),
                  ]"
                  required
                />
                <FormLabel v-if="errors.country_id" class="error-message">{{
                  errors.country_id[0]
                }}</FormLabel>
              </div>
              <div>
                <FormLabel>State</FormLabel>
                <FormDropdown
                  v-model="form.state_id"
                  icon="fas fa-map-marker-alt"
                  @change="onStateChange"
                  :options="[
                    { value: null, text: 'Select', disabled: true },
                    ...states.map((s) => ({ value: s.id, text: s.name })),
                  ]"
                  required
                />
                <FormLabel v-if="errors.state_id" class="error-message">{{
                  errors.state_id[0]
                }}</FormLabel>
              </div>
            </FormRow>
            <FormRow>
              <div>
                <FormLabel>City</FormLabel>
                <FormDropdown
                  v-model="form.city_id"
                  icon="fas fa-map-marker-alt"
                  :options="[
                    { value: null, text: 'Select', disabled: true },
                    ...cities.map((city) => ({
                      value: city.id,
                      text: city.name,
                    })),
                  ]"
                  required
                />
                <FormLabel v-if="errors.city_id" class="error-message">{{
                  errors.city_id[0]
                }}</FormLabel>
              </div>
              <div>
                <FormLabel>Zip Code</FormLabel>
                <FormInput
                  v-model="form.zip_code"
                  icon="fas fa-map-marker-alt"
                  placeholder="Enter PIN code"
                  required
                />
                <FormLabel v-if="errors.zip_code" class="error-message">{{
                  errors.zip_code[0]
                }}</FormLabel>
              </div>
              <div></div>
            </FormRow>
            <div class="lead-capture-actions">
              <button
                type="button"
                class="org-edit-cancel"
                @click="$router.push('/leads')"
              >
                Cancel
              </button>
              <button type="submit" class="org-edit-update">Save Lead</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script>
import {
  FormBox,
  FormDropdown,
  FormInput,
  FormLabel,
  FormRow,
} from "@/components/Common/Common_UI/Form";
import MainLayout from "@/components/layout/MainLayout.vue";
import { orgSizeOptions } from "@/utils/formUtils";
import axios from "axios";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
export default {
  name: "LeadCapture",
  components: {
    MainLayout,
    FormRow,
    FormLabel,
    FormInput,
    FormDropdown,
    FormBox,

    Toast,
  },
  setup() {
    const toast = useToast();
    return { toast };
  },
  data() {
    return {
      showPassword: false,
      referralSources: [],
      orgSizeOptions,
      form: {
        firstName: "",
        lastName: "",
        name: "",
        email: "",
        phone_number: "",
        referral_source_id: null,
        referral_other_text: "",
        organization_name: "",
        organization_size: null,
        address_line_1: "",
        address_line_2: "",
        country_id: null,
        state_id: null,
        city_id: null,
        zip_code: "",
      },
      countries: [],
      states: [],
      cities: [],
      loading: false,
      successMessage: "",
      errorMessage: "",
      errors: {},
    };
  },
  computed: {
    isReferralSourceOther() {
      // Check if the selected referral source is "Other"
      if (!this.form.referral_source_id) return false;
      const selected = this.referralSources.find(
        (r) => r.id === this.form.referral_source_id
      );
      return selected && selected.name && selected.name.toLowerCase() === "other";
    },
  },
  watch: {
    "form.country_id"(val) {
      console.log(
        `[LeadCapture] [FRONTEND] country_id changed:`,
        val,
        "type:",
        typeof val
      );
    },
    "form.referral_source_id"(val) {
      // Clear "other" text when switching away from "Other" option
      if (!this.isReferralSourceOther) {
        this.form.referral_other_text = "";
      }
    },
  },
  methods: {
    togglePassword() {
      this.showPassword = !this.showPassword;
    },
    async fetchReferralSources() {
      const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;
      console.log("[LeadCapture] [FRONTEND] Fetching referral sources...");
      const res = await axios.get(`${API_BASE_URL}/api/referral-sources`);
      this.referralSources = res.data;
      console.log(
        "[LeadCapture] [FRONTEND] Referral sources fetched:",
        this.referralSources
      );
    },
    async fetchCountries() {
      const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;
      console.log("[LeadCapture] [FRONTEND] Fetching countries...");
      const res = await axios.get(`${API_BASE_URL}/api/countries`);
      this.countries = res.data;
      console.log(
        "[LeadCapture] [FRONTEND] Countries fetched:",
        this.countries
      );
    },
    async fetchStates() {
      if (!this.form.country_id) {
        this.states = [];
        console.log(
          "[LeadCapture] [FRONTEND] No country selected, states cleared."
        );
        return;
      }
      const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;
      console.log(
        `[LeadCapture] [FRONTEND] Fetching states for country_id: ${this.form.country_id}`
      );
      const res = await axios.get(
        `${API_BASE_URL}/api/states?country_id=${this.form.country_id}`
      );
      this.states = res.data;
      console.log("[LeadCapture] [FRONTEND] States fetched:", this.states);
    },
    async fetchCities() {
      if (!this.form.state_id) {
        this.cities = [];
        console.log(
          "[LeadCapture] [FRONTEND] No state selected, cities cleared."
        );
        return;
      }
      const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;
      console.log(
        `[LeadCapture] [FRONTEND] Fetching cities for state_id: ${this.form.state_id}`
      );
      const res = await axios.get(
        `${API_BASE_URL}/api/cities?state_id=${this.form.state_id}`
      );
      this.cities = res.data;
      console.log("[LeadCapture] [FRONTEND] Cities fetched:", this.cities);
    },
    onCountryChange() {
      let val = this.form.country_id;

      if (val !== null && val !== "" && typeof val !== "number") {
        const num = Number(val);
        if (!Number.isNaN(num)) {
          this.form.country_id = num;
          val = num;
        }
      }
      console.log(
        `[LeadCapture] [FRONTEND] Country changed:`,
        val,
        "type:",
        typeof val
      );
      if (val && typeof val === "number") {
        this.form.state_id = null;
        this.form.city_id = null;
        this.states = [];
        this.cities = [];
        this.fetchStates();
      } else {
        this.form.state_id = null;
        this.form.city_id = null;
        this.states = [];
        this.cities = [];
        console.log(
          "[LeadCapture] [FRONTEND] No country selected, states cleared."
        );
      }
    },
    onStateChange() {
      let val = this.form.state_id;
      if (val !== null && val !== "" && typeof val !== "number") {
        const num = Number(val);
        if (!Number.isNaN(num)) {
          this.form.state_id = num;
          val = num;
        }
      }
      console.log(
        `[LeadCapture] [FRONTEND] State changed:`,
        val,
        "type:",
        typeof val
      );
      if (val && typeof val === "number") {
        this.form.city_id = null;
        this.cities = [];
        this.fetchCities();
      } else {
        this.form.city_id = null;
        this.cities = [];
        console.log(
          "[LeadCapture] [FRONTEND] No state selected, cities cleared."
        );
      }
    },
    async handleSaveLead() {
      this.loading = true;
      this.successMessage = "";
      this.errorMessage = "";
      try {
        const storage = require("@/services/storage").default;
        const token = storage.get("authToken");
        if (!token) {
          this.errorMessage = "Authentication token not found. Please log in.";
          this.loading = false;
          return;
        }

        const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;
        // build payload with all lead fields
        const payload = {
          first_name: this.form.firstName,
          last_name: this.form.lastName,
          email: this.form.email,
          phone_number: this.form.phone_number,
          referral_source_id: this.form.referral_source_id,
          referral_other_text: this.form.referral_other_text,
          organization_name: this.form.organization_name,
          organization_size: this.form.organization_size,
          address_line_1: this.form.address_line_1,
          address_line_2: this.form.address_line_2,
          zip_code: this.form.zip_code,
          country_id: this.form.country_id,
          state_id: this.form.state_id,
          city_id: this.form.city_id,
          status: "Lead Stage",
          create_organization: true, // Flag to tell backend to create organization
        };

        // Remove keys with null/empty-string values so backend doesn't validate them
        for (const k of Object.keys(payload)) {
          const v = payload[k];
          if (v === null || v === "" || (Array.isArray(v) && v.length === 0)) {
            delete payload[k];
          }
        }

        // include password only when provided
        if (this.form.password && this.form.password !== "") {
          payload.password = this.form.password;
        }

        const response = await axios.post(
          `${API_BASE_URL}/api/leads`,
          payload,
          {
            headers: {
              Authorization: `Bearer ${token}`,
            },
          }
        );
        this.successMessage =
          response.data.message || "Lead saved successfully!";
        this.resetForm();
        this.$router.push("/leads");
      } catch (error) {
        console.error("Error saving lead:", error);

        if (error.response?.data) {
          const { message, errors } = error.response.data;

          this.errorMessage = message || "Failed to save lead.";
          this.errors = errors || {};
        } else {
          this.errorMessage = "An unexpected error occurred.";
        }

        // Use PrimeVue Toast for error notification
        this.$toast.add({
          severity: "error",
          summary: "Validation Error",
          detail: this.errorMessage,
          life: 5000,
        });
      } finally {
        this.loading = false;
      }
    },
    resetForm() {
      this.form = {
        firstName: "",
        lastName: "",
        email: "",
        phone_number: "",
        password: "",
        referral_source_id: null,
        referral_other_text: "",
        organization_name: "",
        organization_size: null,
        address_line_1: "",
        address_line_2: "",
        country_id: null,
        state_id: null,
        city_id: null,
        zip_code: "",
      };
      this.states = [];
      this.cities = [];
    },
    // Prefill form from query params (support canonical `name`)
    loadFromQuery() {
      const q = this.$route.query || {};
      // prefer q.name, then q.first_name/last_name, then q.contact split
      if (q.name || q.first_name || q.contact) {
        let first = q.first_name || null;
        let last = q.last_name || null;
        if (!first && !last && q.name) {
          const parts = (q.name || "").trim().split(/\s+/);
          first = parts.shift() || "";
          last = parts.join(" ") || "";
        }
        if (!first && !last && q.contact) {
          first = q.contact.split(" ")[0] || "";
          last = q.contact.split(" ")[1] || "";
        }

        this.form.firstName = first || "";
        this.form.lastName = last || "";
        this.form.name =
          q.name || `${this.form.firstName} ${this.form.lastName}`.trim();
        this.form.email = q.email || this.form.email;
        this.form.phone_number =
          q.phone_number || q.phone || this.form.phone_number;
        this.form.referral_source_id =
          q.referral_source_id || this.form.referral_source_id;
        this.form.organization_name =
          q.organization || q.organization_name || this.form.organization_name;
        this.form.organization_size =
          q.size || q.organization_size || this.form.organization_size;
        this.form.address_line_1 =
          q.address_line_1 || q.address || this.form.address_line_1;
        this.form.address_line_2 = q.address_line_2 || this.form.address_line_2;
        this.form.country_id = q.country_id || this.form.country_id;
        this.form.state_id = q.state_id || this.form.state_id;
        this.form.city_id = q.city_id || this.form.city_id;
        this.form.zip_code = q.zip_code || q.zip || this.form.zip_code;
      }
    },
  },
  mounted() {
    this.fetchReferralSources();
    this.fetchCountries();
    // If registration link provided canonical `name` or other query params, prefill
    this.loadFromQuery();
  },
};
</script>

<style scoped>
.lead-capture-outer {
  width: 100%;

  min-width: 260px;

  display: flex;
  flex-direction: column;
  align-items: center;
  box-sizing: border-box;
  background: none !important;
  padding: 0;
}

.lead-capture-card {
  width: 100%;

  min-width: 240px;
  background: #fff;
  border-radius: 24px;
  border: 1px solid #ebebeb;
  box-shadow: 0 2px 16px 0 rgba(33, 150, 243, 0.04);
  margin: 0 auto;
  box-sizing: border-box;
  padding: 32px 32px 24px 32px;
  display: flex;
  flex-direction: column;
  gap: 32px;
  position: relative;
}
@media (max-width: 600px) {
  .lead-capture-card {
    padding: 8px;
  }
}

.lead-capture-card-title {
  font-size: 22px;
  font-weight: 600;
  margin-bottom: 24px;
  text-align: left;
  width: 100%;
}

.lead-capture-form {
  width: 100%;
  min-width: 200px;
}

.lead-capture-actions {
  display: flex;
  justify-content: flex-end;
  gap: 18px;
  min-width: 240px;
}

.form-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 16px;
  color: #222;
  width: 100%;
  height: 44px;
  padding: 0 36px 0 32px; /* left for lock, right for eye */
  font-family: inherit;
  box-sizing: border-box;
}
.form-input:disabled {
  background: #f0f0f0;
  color: #6b6b6b;
}
.form-input-icon {
  position: absolute;
  left: 12px;
  color: #888;
  font-size: 18px;
  display: flex;
  align-items: center;
  height: 100%;
  z-index: 2;
  pointer-events: none;
}
.input-eye {
  position: absolute;
  right: 12px;
  color: #888;
  font-size: 18px;
  cursor: pointer;
  z-index: 3;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  padding: 0;
}
.form-box {
  position: relative;
  display: flex;
  align-items: center;
  background: #f6f6f6;
  border-radius: 10px;
  border: 1.5px solid #e0e0e0;
  padding: 0;
  min-height: 48px;
  margin-bottom: 0;
  box-sizing: border-box;
  transition: border 0.18s;
}

.org-edit-actions {
  display: flex;
  justify-content: flex-end;
  gap: 18px;
  min-width: 240px;
}

.org-edit-cancel {
  background: #f5f5f5;
  color: #6b6b6b;
  border: none;
  border-radius: 24px;
  padding: 10px 32px;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}
.org-edit-cancel:hover {
  background: #e0e0e0;
}
.org-edit-update {
  background: #0074c2;
  color: #fff;
  border: none;
  border-radius: 24px;
  padding: 10px 32px;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}
.org-edit-update:hover {
  background: #005fa3;
}
.error-message {
  color: red;
  font-size: 0.8em;
  margin-top: 10px;
}
</style>
