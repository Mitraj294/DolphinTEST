<template>
  <MainLayout>
    <Toast />
    <div class="page">
      <OrgAdminGraphs v-if="isOrgAdmin" />
      <UserGraphs v-else-if="isUser" />
    </div>
  </MainLayout>
</template>

<script>
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import MainLayout from "../../layout/MainLayout.vue";
import OrgAdminGraphs from "./OrgAdminGraphs.vue";
import UserGraphs from "./UserGraphs.vue";

export default {
  components: {
    MainLayout,
    OrgAdminGraphs,
    UserGraphs,
    Toast,
  },
  setup() {
    const toast = useToast();
    return { toast };
  },
  computed: {
    isOrgAdmin() {
      const storage = require("@/services/storage").default;
      const role = storage.get("role");
      return (
        role === "organizationadmin" ||
        role === "superadmin" ||
        role === "Dolphinadmin"
      );
    },
    isUser() {
      const storage = require("@/services/storage").default;
      const role = storage.get("role");
      return role === "user" || role === "salesperson";
    },
  },
};
</script>
