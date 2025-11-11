<template>
  <MainLayout>
    <div class="page">
      <div class="assessments-table-outer">
        <OrganizationAdminAssessmentsCard v-if="isOrganizationAdmin" />
        <UserAssessment v-else-if="isUser" />
      </div>
    </div>
  </MainLayout>
</template>

<script>
import MainLayout from '@/components/layout/MainLayout.vue';
import OrganizationAdminAssessmentsCard from './OrganizationAdminAssessmentsCard.vue';
import UserAssessment from './UserAssessment.vue';
import storage from '@/services/storage';

export default {
  name: 'Assessments',
  components: { MainLayout, OrganizationAdminAssessmentsCard, UserAssessment },
  computed: {
    role() {
      try {
        return (storage.get('role') || 'user').toString().toLowerCase();
      } catch (e) {
        console.debug && console.debug('Assessments: failed to read role from storage', e);
        return 'user';
      }
    },
    isOrganizationAdmin() {
      return this.role === 'organizationadmin';
    },
    isUser() {
      return this.role === 'user';
    },
  },
};
</script>

<style scoped>
.assessments-table-outer {
  width: 100%;
  min-width: 260px;

  display: flex;
  flex-direction: column;
  align-items: center;
  box-sizing: border-box;
  background: none !important;
  padding: 0 !important;
}
</style>
