<template>
  <MainLayout>
    <div class="page">
      <div class="lead-detail-outer">
        <div class="lead-detail-main-card">
          <div class="lead-detail-main-card-header">
            <button class="btn btn-primary" @click="goToEditLead">
              Edit Details
            </button>
          </div>
          <div class="lead-detail-main-cols">
            <div
              class="lead-detail-main-cols-group lead-detail-main-cols-group--row"
            >
              <div class="lead-detail-col lead-detail-col-left">
                <h3 class="lead-detail-section-title">Lead Detail</h3>
                <div class="lead-detail-list-card lead-detail-list-card--box">
                  <div class="lead-detail-list-row">
                    <span>Main Contact</span><b>{{ leadData.contact }}</b>
                  </div>
                  <div class="lead-detail-list-row">
                    <span>Admin Email</span><b>{{ leadData.email }}</b>
                  </div>
                  <div class="lead-detail-list-row">
                    <span>Admin Phone</span><b>{{ leadData.phone }}</b>
                  </div>
                  <div class="lead-detail-list-row">
                    <span>Sales Person</span
                    ><b>{{
                      orgData?.sales_person || leadData.sales_person || "N/A"
                    }}</b>
                  </div>
                  <div class="lead-detail-list-row">
                    <span>Source</span
                    ><b>{{
                      orgData?.referral_source || leadData.source || "N/A"
                    }}</b>
                  </div>
                  <div class="lead-detail-list-row">
                    <span>Status</span><b>{{ leadData.status }}</b>
                  </div>
                </div>
              </div>
              <div class="lead-detail-col lead-detail-col-right">
                <h3 class="lead-detail-section-title">Organization Detail</h3>
                <div class="lead-detail-list-card lead-detail-list-card--box">
                  <template v-if="organizationChecked && isOrganizationCreated">
                    <div class="lead-detail-list-row">
                      <span>Organization Name</span
                      ><b>{{
                        orgData.name ||
                        orgData.organization_name ||
                        leadData.organization
                      }}</b>
                    </div>
                    <div class="lead-detail-list-row">
                      <span>Organization Size</span>
                      <b>{{
                        orgData.size ||
                        orgData.organization_size ||
                        leadData.size
                      }}</b>
                    </div>
                    <div class="lead-detail-list-row">
                      <span>Contract Start</span>
                      <b>{{
                        formatContractDate(
                          orgData?.contract_start ||
                            orgData?.contract_start_date ||
                            leadData.contract_start
                        ) || "N/A"
                      }}</b>
                    </div>
                    <div class="lead-detail-list-row">
                      <span>Contract End</span>
                      <b>{{
                        formatContractDate(
                          orgData?.contract_end ||
                            orgData?.contract_end_date ||
                            leadData.contract_end
                        ) || "N/A"
                      }}</b>
                    </div>
                    <div class="lead-detail-list-row">
                      <span>Address</span>
                      <b>{{
                        orgData?.address_display ||
                        addressDisplay.join(", ") ||
                        "N/A"
                      }}</b>
                    </div>
                  </template>
                  <template v-else>
                    <div class="lead-detail-list-row">
                      <span>Organization Name</span
                      ><b>{{ leadData.organization }}</b>
                    </div>
                    <div class="lead-detail-list-row">
                      <span>Organization Size</span>
                      <b>{{ leadData.size }}</b>
                    </div>
                    <div class="lead-detail-list-row">
                      <span>Contract Start</span
                      ><b>{{
                        formatContractDate(leadData.contract_start) || "N/A"
                      }}</b>
                    </div>
                    <div class="lead-detail-list-row">
                      <span>Contract End</span
                      ><b>{{
                        formatContractDate(leadData.contract_end) || "N/A"
                      }}</b>
                    </div>
                    <div class="lead-detail-list-row">
                      <span>Address</span>
                      <b>
                      
                        <template v-if="orgData && orgData.address">
                          <template v-if="orgData.address_display && orgData.address_display !== 'N/A'">
                            {{ orgData.address_display }}
                          </template>
                          <template v-else>
                            {{
                              [
                                orgData.address.address_line_1,
                                orgData.address.address_line_2,
                                orgData.address.city?.name || orgData.address.city,
                                orgData.address.state?.name || orgData.address.state,
                                orgData.address.zip_code
                              ].filter(Boolean).join(', ') || (addressDisplay.length ? addressDisplay.join(', ') : 'N/A')
                            }}
                          </template>
                        </template>

                        <template v-else-if="addressDisplay.length">
                          {{ addressDisplay.join(', ') }}
                        </template>

                        <template v-else>N/A</template>
                      </b>
                    </div>
                  </template>
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
import MainLayout from "@/components/layout/MainLayout.vue";
import storage from "@/services/storage";
import axios from "axios";
/*
  Refactor notes:
  - Extracted complex logic from the `created()` lifecycle hook into small helpers:
    `loadLeadById`, `normalizeLeadObj`, `initFromPayload`, `initFromQuery`.
  - This reduces cognitive complexity and makes the initialization flow easier to test.
  - Behavior preserved; API calls and fallbacks remain the same.
*/
export default {
  name: "LeadDetail",
  components: { MainLayout },
  props: {
    lead: {
      type: Object,
      default: () => ({
        contact: "",
        email: "",
        phone: "",
        source: "",
        sales_person: "",
        sales_person_id: null,
        status: "",
        organization: "",
        size: "",
        address: "",
        city: "",
        state: "",
        zip: "",
        country: "",
        country_id: null,
        state_id: null,
        city_id: null,
      }),
    },
  },
  data() {
    return {
      localLead: { ...this.lead },
      countryName: "",
      stateName: "",
      cityName: "",
      referralSources: [],
      // organization related
      orgData: null,
      orgUser: null,
      orgUserDetails: null,
      isOrganizationCreated: false,
      organizationChecked: false,
    };
  },
  computed: {
    leadData() {
      return this.localLead;
    },
    addressDisplay() {
      // Compose address with looked-up names
      const arr = [];
      if (this.leadData.address) arr.push(this.leadData.address);
      if (this.cityName) arr.push(this.cityName);
      if (this.stateName) arr.push(this.stateName);
      if (this.leadData.zip) arr.push(this.leadData.zip);
      if (this.countryName) arr.push(this.countryName);
      return arr.filter((f) => f && String(f).trim().length > 0);
    },
    primaryUserDisplay() {
      // Prefer orgUser.name, then orgUser.full_name, then user details names, then lead contact, then N/A
      if (this.orgUser && (this.orgUser.name || this.orgUser.full_name)) {
        return this.orgUser.name || this.orgUser.full_name;
      }
      if (
        this.orgUserDetails &&
        (this.orgUserDetails.first_name || this.orgUserDetails.last_name)
      ) {
        const fn = this.org || "";
        const ln = this.orgUserDetails.last_name || "";
        const full = (fn + " " + ln).trim();
        if (full) return full;
      }
      if (this.leadData && this.leadData.contact) return this.leadData.contact;
      return "N/A";
    },
  },
  methods: {
    goToEditLead() {
      const id =
        this.$route.params.id || this.$route.query.id || this.leadData.id || "";
      if (id) {
        this.$router.push({ name: "EditLead", params: { id } });
      }
    },
    formatContractDate(dateVal) {
      if (dateVal) {
        // accept timestamps or 'YYYY-MM-DD' or Date objects
        const d = new Date(dateVal);
        if (Number.isNaN(d.getTime())) return null;
        const day = String(d.getDate()).padStart(2, "0");
        const months = [
          "JAN",
          "FEB",
          "MAR",
          "APR",
          "MAY",
          "JUN",
          "JUL",
          "AUG",
          "SEP",
          "OCT",
          "NOV",
          "DEC",
        ];
        const mon = months[d.getMonth()];
        const yr = d.getFullYear();
        // format like: 31 AUG ,2025 (kept spacing to match your example)
        return `${day} ${mon} ,${yr}`;
      }
      return null;
    },
    async lookupLocationNames() {
      const API_BASE_URL = process.env.VUE_APP_API_BASE_URL || "";
      if (this.leadData.country_id) {
        try {
          const res = await axios.get(
            `${API_BASE_URL}/api/countries/${this.leadData.country_id}`
          );
          this.countryName = res.data?.name || "";
        } catch (e) {
          console.error("Error fetching country name:", e);
          this.countryName = "";
        }
      } else {
        this.countryName = this.leadData.country || "";
      }
      if (this.leadData.state_id) {
        try {
          const res = await axios.get(
            `${API_BASE_URL}/api/states/${this.leadData.state_id}`
          );
          this.stateName = res.data?.name || "";
        } catch (e) {
          console.error("Error fetching state name:", e);
          this.stateName = "";
        }
      } else {
        this.stateName = this.leadData.state || "";
      }
      if (this.leadData.city_id) {
        try {
          const res = await axios.get(
            `${API_BASE_URL}/api/cities/${this.leadData.city_id}`
          );
          this.cityName = res.data?.name || "";
        } catch (e) {
          console.error("Error fetching city name:", e);
          this.cityName = "";
        }
      } else {
        this.cityName = this.leadData.city || "";
      }
    },
    async fetchOrganizationIfExists() {
      // Assumptions: backend exposes /api/organizations/:id and /api/users/:id and /api/user-details/:id
      // Detect organisation id on the lead as organization_id, org_id or organizationId
      this.organizationChecked = false;
      this.orgData = null;
      this.orgUser = null;
      this.orgUserDetails = null;
      this.isOrganizationCreated = false;
      const orgId =
        this.localLead.organization_id ||
        this.localLead.org_id ||
        this.localLead.organizationId ||
        null;
      const userId =
        this.localLead.user_id ||
        this.localLead.userId ||
        this.localLead.owner_id ||
        null;
      if (orgId) {
        try {
          const API_BASE_URL = process.env.VUE_APP_API_BASE_URL || "";
          const token = storage.get("authToken");
          const headers = token ? { Authorization: `Bearer ${token}` } : {};
          // fetch organization
          const orgRes = await axios.get(
            `${API_BASE_URL}/api/organizations/${orgId}`,
            { headers }
          );
          this.orgData = orgRes.data || null;
          this.isOrganizationCreated = !!this.orgData;
          // fetch user and user details if userId present
          if (userId) {
            try {
              const userRes = await axios.get(
                `${API_BASE_URL}/api/users/${userId}`,
                { headers }
              );
              this.orgUser = userRes.data || null;
            } catch (e) {
              console.error("Error fetching organization user:", e);
              this.orgUser = null;
            }
            try {
              const detailsRes = await axios.get(
                `${API_BASE_URL}/api/user-details/${userId}`,
                { headers }
              );
              this.orgUserDetails = detailsRes.data || null;
            } catch (e) {
              console.error("Error fetching organization user details:", e);
              this.orgUserDetails = null;
            }
          }
        } catch (e) {
          console.error("Error fetching organization:", e);
          this.orgData = null;
          this.isOrganizationCreated = false;
        } finally {
          this.organizationChecked = true;
        }
      } else {
        this.isOrganizationCreated = false;
        this.organizationChecked = true;
      }
    },
    // Normalize a lead object coming from backend into the localLead shape
    normalizeLeadObj(leadObj) {
      return {
        contact:
          (leadObj.first_name || "") +
          (leadObj.last_name ? " " + leadObj.last_name : ""),
        email: leadObj.email || "",
        phone: leadObj.phone_number || leadObj.phone || "",
        source: leadObj.find_us || "",
        sales_person: leadObj.sales_person || "",
        sales_person_id: leadObj.sales_person_id || null,
        status: leadObj.status || "",
        organization: leadObj.organization_name || "",
        size: leadObj.organization_size || "",
        address: leadObj.address ?? "",
        city: leadObj.city ?? "",
        state: leadObj.state ?? "",
        zip: leadObj.zip ?? "",
        country: leadObj.country ?? "",
        country_id: leadObj.country_id || null,
        state_id: leadObj.state_id || null,
        city_id: leadObj.city_id || null,
        id: leadObj.id || null,
        first_name: leadObj.first_name || "",
        last_name: leadObj.last_name || "",
        organization_id: leadObj.organization_id || null,
        user_id: leadObj.user_id || null,
      };
    },

    async fetchReferralSources() {
      try {
        const API_BASE_URL = process.env.VUE_APP_API_BASE_URL || "";
        const res = await axios.get(`${API_BASE_URL}/api/referral-sources`);
        this.referralSources = res.data || res.data?.options || [];
      } catch (e) {
        console.warn("Failed to fetch referral sources", e);
        this.referralSources = [];
      }
    },

    mapReferralSourceName(leadObj) {
      if (!leadObj) return "";
      if (leadObj.find_us) return leadObj.find_us;
      const id = leadObj.referral_source_id || null;
      if (!id) return "";
      if (Array.isArray(this.referralSources) && this.referralSources.length) {
        const found = this.referralSources.find(
          (r) => String(r.id) === String(id)
        );
        return found ? found.name || found.text || String(id) : String(id);
      }
      return String(id);
    },

    // Load lead payload from API by id. Returns payload or null on failure.
    async loadLeadById(id) {
      if (id) {
        try {
          const API_BASE_URL = process.env.VUE_APP_API_BASE_URL || "";
          const token = storage.get("authToken");
          const res = await axios.get(`${API_BASE_URL}/api/leads/${id}`, {
            headers: { Authorization: `Bearer ${token}` },
          });
          return res.data || null;
        } catch (e) {
          console.error("Error fetching lead details", e);
          return null;
        }
      }
      return null;
    },

    // Initialize component state from API payload
    async initFromPayload(payload, id) {
      const leadObj = payload.lead ? payload.lead : payload;
      this.localLead = this.normalizeLeadObj(leadObj);
      // Resolve referral source name (id -> name) when possible
      await this.fetchReferralSources();
      this.localLead.source =
        this.mapReferralSourceName(leadObj) || this.localLead.source;
      if (payload.organization) {
        this.orgData = payload.organization;
        this.orgUser = payload.orgUser || null;
        this.orgUserDetails = payload.orgUserDetails || null;
        this.isOrganizationCreated = true;
        this.organizationChecked = true;
      }
      for (const f of ["address", "city", "state", "zip", "country"]) {
        if (this.localLead[f] === undefined) this.localLead[f] = "";
      }
      await this.lookupLocationNames();
      if (this.isOrganizationCreated) {
        // organization already present in payload
      } else {
        await this.fetchOrganizationIfExists();
      }
      // If there were extra query params, replace URL to canonical route
      try {
        if (
          this.$route &&
          this.$route.query &&
          Object.keys(this.$route.query).length
        ) {
          this.$router.replace({ name: "LeadDetail", params: { id } });
        }
      } catch (e) {
        console.warn("Failed to replace route", e);
      }
    },

    // Initialize component state from route query params
    async initFromQuery() {
      if (
        this.$route &&
        this.$route.query &&
        Object.keys(this.$route.query).length
      ) {
        const q = this.$route.query;
        this.localLead = {
          contact: q.contact || "",
          email: q.email || "",
          phone: q.phone || "",
          source: q.source || "",
          sales_person: q.sales_person || "",
          sales_person_id: q.sales_person_id || null,
          status: q.status || "",
          organization: q.organization || "",
          size: q.size || "",
          address: q.address ?? "",
          city: q.city ?? "",
          state: q.state ?? "",
          zip: q.zip ?? "",
          country: q.country ?? "",
          country_id: q.country_id || null,
          state_id: q.state_id || null,
          city_id: q.city_id || null,
        };
        for (const f of ["address", "city", "state", "zip", "country"]) {
          if (this.localLead[f] === undefined) this.localLead[f] = "";
        }
        await this.lookupLocationNames();
        await this.fetchOrganizationIfExists();
      }
    },
  },
  async created() {
    const id = this.$route.params.id || this.$route.query.id || this.lead.id;
    if (id) {
      const payload = await this.loadLeadById(id);
      if (payload) {
        await this.initFromPayload(payload, id);
        return;
      }
      // if fetch failed, fallthrough to use fallback lead data
      this.localLead = { ...this.lead };
      await this.lookupLocationNames();
      await this.fetchOrganizationIfExists();
      return;
    }
    // no id: try to initialize from query params
    await this.initFromQuery();
  },

  watch: {
    "$route.query": {
      handler(newQuery) {
        if (newQuery && Object.keys(newQuery).length) {
          this.localLead = {
            contact: newQuery.contact || "",
            email: newQuery.email || "",
            phone: newQuery.phone || "",
            source: newQuery.source || "",
            sales_person: newQuery.sales_person || "",
            sales_person_id: newQuery.sales_person_id || null,
            status: newQuery.status || "",
            organization: newQuery.organization || "",
            size: newQuery.size || "",
            address: newQuery.address ?? "",
            city: newQuery.city ?? "",
            state: newQuery.state ?? "",
            zip: newQuery.zip ?? "",
            country: newQuery.country ?? "",
            country_id: newQuery.country_id || null,
            state_id: newQuery.state_id || null,
            city_id: newQuery.city_id || null,
          };
          for (const f of ["address", "city", "state", "zip", "country"]) {
            if (this.localLead[f] === undefined) this.localLead[f] = "";
          }
          this.lookupLocationNames();
          this.fetchOrganizationIfExists();
        }
      },
      deep: true,
    },
    lead: {
      handler(newLead) {
        this.localLead = { ...newLead };
        this.lookupLocationNames();
        this.fetchOrganizationIfExists();
      },
      deep: true,
    },
  },
};
</script>

<style scoped>
.lead-detail-outer {
  width: 100%;

  min-width: 260px;

  display: flex;
  flex-direction: column;
  align-items: center;
  box-sizing: border-box;
  background: none !important;
  padding: 0;
}

.lead-detail-main-card {
  width: 100%;

  min-width: 0;
  background: #fff;
  border-radius: 24px;
  border: 1px solid #ebebeb;
  box-sizing: border-box;
  overflow: visible;
  box-shadow: 0 2px 16px 0 rgba(33, 150, 243, 0.04);
  margin: 0 auto;
  padding: 32px 32px 24px 32px;
  display: flex;
  flex-direction: column;
  gap: 32px;
  position: relative;
}

.lead-detail-main-card-header {
  width: 100%;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  margin-bottom: 8px;
  min-height: 0;
}

.lead-detail-main-cols {
  display: flex;
  flex-direction: column;
  gap: 32px;
  width: 100%;
  min-width: 240px;
  max-width: 100%;
  justify-content: center;
  align-items: stretch;
  margin-bottom: 0;
}

.lead-detail-main-cols-group {
  display: flex;
  flex-direction: row;
  gap: 64px;
  width: 100%;
}

.lead-detail-main-cols-group--row {
  flex-direction: row;
  gap: 32px;
  margin-top: 0;
  margin-bottom: 0;
}

.lead-detail-col {
  flex: 1 1 0;
  min-width: 0;
  max-width: 100%;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  margin: 0;
}

.lead-detail-section-title {
  font-family: "Helvetica Neue LT Std", Helvetica, Arial, sans-serif;
  font-weight: 600;
  font-size: 20px;
  color: #222;
  margin-bottom: 18px;
  margin-top: 0;
  text-align: left;
  width: 100%;
}

.lead-detail-list-card--box {
  border-radius: 20px;
  background: #f8f8f8;
  padding: 24px 32px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  font-size: 18px;
  margin: 10px;
  box-sizing: border-box;
  width: 100%;
  min-width: 240px;
  max-width: 100%;
  min-height: 270px;
  justify-content: flex-start;
}

.lead-detail-list-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0;
  flex-wrap: wrap;
  word-break: break-word;
  padding: 2px 0;
}

.lead-detail-list-row span {
  color: #555;
  font-weight: 400;
  min-width: 160px;
  text-align: left;
  font-size: 19px;
  font-family: "Inter", Arial, sans-serif;
  line-height: 1.7;
  letter-spacing: 0.01em;
  flex: 1 1 50%;
}

.lead-detail-list-row b {
  color: #222;
  font-weight: 600;
  text-align: left;
  word-break: break-word;
  font-size: 17px;
  font-family: "Inter", Arial, sans-serif;
  line-height: 1.7;
  letter-spacing: 0.01em;
  flex: 1 1 50%;
  justify-content: flex-start;
  display: flex;
}

@media (max-width: 900px) {
  .lead-detail-main-card {
    padding: 8px;
    border-radius: 10px;
  }

  .lead-detail-main-cols {
    flex-direction: column;
    gap: 0;
  }

  .lead-detail-main-cols-group {
    flex-direction: column;
    gap: 0;
    width: 100%;
    margin-bottom: 18px;
  }

  .lead-detail-main-cols-group--row {
    flex-direction: column;
    gap: 0;
    width: 100%;
    margin-bottom: 18px;
  }

  .lead-detail-col {
    min-width: 0;
    max-width: 100%;
    width: 100%;
    margin: 0 0 18px 0;
  }

  .lead-detail-list-card--box {
    padding: 8px;
    margin: 0;
    font-size: 12px;
    gap: 6px;
    min-height: 0;
    min-width: 240px;

    width: 100%;
  }
}
</style>
