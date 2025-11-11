/**
  Dolphin Project - Vue Router Configuration
  
  Implements static and dynamic route imports, navigation guards, 
  subscription flow, role-based access, and guest token support.
 
  Structure:
 Static imports: For core, frequently-used components.
 Dynamic imports: For heavier/feature components (lazy-loaded).
 Route definitions: Public, authenticated, organization, lead, assessment, superadmin, etc.
 Navigation guards: Auth/role/subscription/guest logic.
 */

import { createRouter, createWebHistory } from "vue-router";

// Service/Utility imports
import { ROLES, canAccess } from "@/permissions"; // Permissions helper
import storage from "@/services/storage"; // Services folder for storage utils
import { fetchSubscriptionStatus } from "@/services/subscription"; // Subscription service

// STATIC COMPONENT IMPORTS (Core/Essential)
import ForgotPassword from "@/components/auth/ForgotPassword.vue";
import Login from "@/components/auth/Login.vue";
import Register from "@/components/auth/Register.vue";
import SimpleRegister from "@/components/auth/SimpleRegister.vue";
import ResetPassword from "@/components/auth/ResetPassword.vue";
import AssessmentAnswerPage from "@/components/Common/AssessmentAnswerPage.vue";
import Dashboard from "@/components/Common/Dashboard/Dashboard.vue";
import Profile from "@/components/Common/Profile.vue";

// DYNAMIC COMPONENT IMPORTS (Lazy-Loaded)
const ThankYou = () => import("@/components/auth/ThankYou.vue");
const ThanksPage = () => import("@/components/Common/ThanksPage.vue");
const TrainingResources = () =>
  import("@/components/Common/TrainingResources.vue");
const GetNotifications = () =>
  import("@/components/Common/GetNotifications.vue");

// Subscription Flow
const SubscriptionSuccess = () =>
  import("@/components/Common/SubscriptionSuccess.vue");
const ManageSubscription = () =>
  import("@/components/Common/ManageSubscription.vue");
const SubscriptionPlans = () =>
  import("@/components/Common/SubscriptionPlans.vue");
const BillingDetails = () => import("@/components/Common/BillingDetails.vue");

// Superadmin
const UserPermission = () =>
  import("@/components/Common/Superadmin/UserPermission.vue");
const AddUser = () => import("@/components/Common/Superadmin/AddUser.vue");
const Notifications = () =>
  import("@/components/Common/Superadmin/Notifications.vue");

// Leads & Assessments
const Leads = () => import("@/components/Common/Leads_Assessment/Leads.vue");
const LeadDetail = () =>
  import("@/components/Common/Leads_Assessment/LeadDetail.vue");
const EditLead = () =>
  import("@/components/Common/Leads_Assessment/EditLead.vue");
const LeadCapture = () =>
  import("@/components/Common/Leads_Assessment/LeadCapture.vue");
const SendAssessment = () =>
  import("@/components/Common/Leads_Assessment/SendAssessment.vue");
const SendAgreement = () =>
  import("@/components/Common/Leads_Assessment/SendAgreement.vue");
const ScheduleDemo = () =>
  import("@/components/Common/Leads_Assessment/ScheduleDemo.vue");
const ScheduleClassTraining = () =>
  import("@/components/Common/Leads_Assessment/ScheduleClassTraining.vue");
const Assessments = () =>
  import("@/components/Common/Leads_Assessment/Assessments.vue");
const AssessmentSummary = () =>
  import("@/components/Common/Leads_Assessment/AssessmentSummary.vue");

// Organizations
const Organizations = () =>
  import("@/components/Common/Organizations/Organizations.vue");
const OrganizationDetail = () =>
  import("@/components/Common/Organizations/OrganizationDetail.vue");
const OrganizationEdit = () =>
  import("@/components/Common/Organizations/OrganizationEdit.vue");
const MyOrganization = () =>
  import("@/components/Common/MyOrganization/MyOrganization.vue");
const MemberListing = () =>
  import("@/components/Common/MyOrganization/MemberListing.vue");

// ROUTE DEFINITIONS
const routes = [
  //PUBLIC ROUTES
  {
    path: "/",
    name: "Login",
    component: Login,
    meta: { public: true, guestOnly: true },
  },
  {
    path: "/register",
    name: "Register",
    component: Register,
    meta: { public: true, guestOnly: true },
  },
  {
    path: "/register-simple",
    name: "RegisterSimple",
    component: SimpleRegister,
    meta: { public: true, guestOnly: true },
  },
  {
    path: "/forgot-password",
    name: "ForgotPassword",
    component: ForgotPassword,
    meta: { public: true },
  },
  {
    path: "/reset-password",
    name: "ResetPassword",
    component: ResetPassword,
    meta: { public: true },
  },
  {
    path: "/thankyou",
    name: "ThankYou",
    component: ThankYou,
    meta: { public: true },
  },
  {
    path: "/thanks",
    name: "ThanksPage",
    component: ThanksPage,
    meta: { public: true },
  },
  {
    path: "/assessment/answer/:token",
    name: "AssessmentAnswerPage",
    component: AssessmentAnswerPage,
    meta: { public: true },
  },

  //AUTHENTICATED ROUTES
  {
    path: "/dashboard",
    name: "Dashboard",
    component: Dashboard,
    meta: { requiresAuth: true },
  },
  {
    path: "/profile",
    name: "Profile",
    component: Profile,
    meta: { requiresAuth: true },
  },

  //  SUBSCRIPTION & BILLING
  {
    path: "/manage-subscription",
    name: "ManageSubscription",
    component: ManageSubscription,
    meta: { requiresAuth: true, roles: [ROLES.ORGANIZATIONADMIN] },
  },
  {
    path: "/subscriptions/plans",
    name: "SubscriptionPlans",
    component: SubscriptionPlans,
    meta: { requiresAuth: true, roles: [ROLES.ORGANIZATIONADMIN] },
  },
  {
    path: "/subscriptions/success",
    name: "SubscriptionSuccess",
    component: SubscriptionSuccess,
    meta: { public: true },
  },
  {
    path: "/organizations/billing-details",
    name: "BillingDetails",
    component: BillingDetails,
    props: true,
    meta: { requiresAuth: true, roles: [ROLES.ORGANIZATIONADMIN] },
  },

  //  ORGANIZATIONS
  {
    path: "/organizations",
    name: "Organizations",
    component: Organizations,
    meta: { requiresAuth: true },
  },
  {
    path: "/organizations/:id",
    name: "OrganizationDetail",
    component: OrganizationDetail,
    props: true,
    meta: { requiresAuth: true },
  },
  {
    path: "/organizations/:id/edit",
    name: "OrganizationEdit",
    component: OrganizationEdit,
    props: true,
    meta: { requiresAuth: true },
  },

  //  MY ORGANIZATION
  {
    path: "/my-organization",
    name: "MyOrganization",
    component: MyOrganization,
    meta: { requiresAuth: true },
  },
  {
    path: "/my-organization/members",
    name: "MemberListing",
    component: MemberListing,
    props: true,
    meta: { requiresAuth: true },
  },

  //LEADS
  {
    path: "/leads",
    name: "Leads",
    component: Leads,
    meta: { requiresAuth: true },
  },
  {
    path: "/leads/lead-capture",
    name: "LeadCapture",
    component: LeadCapture,
    meta: { requiresAuth: true },
  },
  {
    path: "/leads/:id",
    name: "LeadDetail",
    component: LeadDetail,
    props: true,
    meta: { requiresAuth: true },
  },
  {
    path: "/leads/:id/edit",
    name: "EditLead",
    component: EditLead,
    props: true,
    meta: { requiresAuth: true },
  },

  //  ASSESSMENTS
  {
    path: "/assessments",
    name: "Assessments",
    component: Assessments,
    meta: { requiresAuth: true },
  },
  {
    path: "/assessments/send-assessment/:id?",
    name: "SendAssessment",
    component: SendAssessment,
    props: true,
    meta: { requiresAuth: true },
  },
  {
    path: "/assessments/send-agreement/:id?",
    name: "SendAgreement",
    component: SendAgreement,
    props: true,
    meta: { requiresAuth: true },
  },
  {
    path: "/assessments/:assessmentId/summary",
    name: "AssessmentSummary",
    component: AssessmentSummary,
    props: true,
    meta: { requiresAuth: true },
  },

  //SUPERADMIN
  {
    path: "/user-permission",
    name: "UserPermission",
    component: UserPermission,
    meta: { requiresAuth: true, roles: [ROLES.SUPERADMIN] },
  },
  {
    path: "/user-permission/add",
    name: "AddUser",
    component: AddUser,
    meta: { requiresAuth: true, roles: [ROLES.SUPERADMIN] },
  },
  {
    path: "/notifications",
    name: "Notifications",
    component: Notifications,
    meta: { requiresAuth: true, roles: [ROLES.SUPERADMIN] },
  },

  //OTHER AUTH ROUTES
  {
    path: "/training-resources",
    name: "TrainingResources",
    component: TrainingResources,
    meta: { requiresAuth: true },
  },
  {
    path: "/get-notification",
    name: "GetNotification",
    component: GetNotifications,
    meta: { requiresAuth: true },
  },
  {
    path: "/leads/schedule-demo",
    name: "ScheduleDemo",
    component: ScheduleDemo,
    meta: { requiresAuth: true },
  },
  {
    path: "/leads/schedule-class-training",
    name: "ScheduleClassTraining",
    component: ScheduleClassTraining,
    meta: { requiresAuth: true },
  },

  //CATCH-ALL ROUTE
  {
    path: "/:catchAll(.*)",
    redirect: "/dashboard",
  },
];

// ROUTER INSTANCE
const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes,
});

// NAVIGATION GUARDS

/**
  Handles navigation for public routes.
 Redirects authenticated users away from guest-only pages.
 */
const handlePublicRoutes = (to, authToken, next) => {
  if (!to.meta.public) return false;
  if (authToken && to.meta.guestOnly) {
    next("/dashboard");
  } else {
    next();
  }
  return true;
};

/**
  Previously this validated guest tokens by calling /api/leads/guest-validate.
  That endpoint / flow is no longer used in the current deployment, so this
  helper is intentionally a no-op to avoid unnecessary network calls and 403s.
 */
const validateGuestToken = async (_opts) => {
  // No-op: legacy lead/guest validation removed. Return false to signal
  // validation did not occur.
  return false;
};

/**
 * Try to validate guest_token or guest_code for the SubscriptionPlans route.
 * Returns true when validation succeeded (caller should `next()`), false otherwise.
 */
const tryValidateGuestForPlans = async (_to) => {
  // Legacy guest/lead flow removed. Do not call backend for guest validation.
  return false;
};

/**
  Handles navigation for expired subscriptions.
 */
const handleExpiredSubscription = (to, next) => {
  const allowedRoutesForExpired = [
    "Profile",
    "ManageSubscription",
    "SubscriptionPlans",
    "BillingDetails",
    "GetNotification",
  ];
  if (allowedRoutesForExpired.includes(to.name)) {
    next();
  } else {
    next("/manage-subscription");
  }
};

/**
  Checks permissions and proceeds or redirects.
 */
const checkPermissionsAndNavigate = (to, role, next) => {
  if (to.meta.roles && !to.meta.roles.includes(role)) {
    return next("/dashboard");
  }
  if (canAccess(role, "routes", to.path)) {
    return next();
  }
  return next("/dashboard");
};

/**
  Handles navigation for authenticated routes, including subscription logic.
 */
const handleAuthenticatedRoutes = async (to, role, next) => {
  // Only allow BillingDetails unconditionally in this early path.
  // ManageSubscription and SubscriptionPlans must pass regular permission checks.
  const subscriptionPages = ["BillingDetails"];
  if (subscriptionPages.includes(to.name)) {
    return next();
  }
  try {
    const subscriptionStatus = await fetchSubscriptionStatus();
    storage.set("subscription_status", subscriptionStatus.status);

    if (subscriptionStatus.status === "expired") {
      handleExpiredSubscription(to, next);
      return;
    }
    if (
      subscriptionStatus.status === "none" &&
      role === ROLES.ORGANIZATIONADMIN
    ) {
      handleExpiredSubscription(to, next);
      return;
    }
    checkPermissionsAndNavigate(to, role, next);
  } catch (error) {
    console.error("Error fetching subscription status:", error);
    storage.clear();
    next("/");
  }
};

/**
  Global navigation guard
 */
router.beforeEach(async (to, from, next) => {
  const authToken = storage.get("authToken");
  // Normalize role to a lowercase string to avoid casing mismatches
  const rawRole = storage.get("role");
  const role = rawRole ? String(rawRole).toLowerCase() : "";

  // Defensive: explicitly block access to known subscription routes
  // so typing the URL directly never shows the page to non-org-admins.
  const subscriptionOnlyRoutes = ["ManageSubscription", "SubscriptionPlans", "BillingDetails"];
  if (subscriptionOnlyRoutes.includes(to.name) && role !== ROLES.ORGANIZATIONADMIN) {
    return next("/dashboard");
  }

  // Handle public routes (Login, Register, etc)
  if (handlePublicRoutes(to, authToken, next)) {
    return;
  }
  // Do not special-case SubscriptionPlans based on query params. Allow the
  // normal permission checks to run so only authorized roles (organizationadmin)
  // can access subscription pages.

  // Authenticated user flow
  if (authToken) {
    await handleAuthenticatedRoutes(to, role, next);
  } else {
    // Try to validate guest token/code as a fallback (non-blocking path). If
    // it succeeds we continue; otherwise we'll fallthrough to the default
    // redirect to login.
    const validated = await tryValidateGuestForPlans(to);
    if (validated) {
      next();
      return;
    }

    // Default: redirect to login
    next("/");
  }
});

export default router;
