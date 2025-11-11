<template>
  <div class="login-bg">
    <Toast />
    <div class="login-card">
      <h2 class="login-title">Create UserAccount</h2>

      <form @submit.prevent="handleRegister">
        <div style="margin-bottom: 16px">
          <FormLabel>First Name</FormLabel>
          <div class="input-group name-group">
            <span class="icon"><i class="fas fa-user"></i></span>
            <input
              type="text"
              v-model="first_name"
              placeholder="First Name"
              ref="firstNameInput"
              required
            />
            <FormLabel v-if="errors.first_name" class="error-message1">{{
              errors.first_name[0]
            }}</FormLabel>
          </div>
        </div>

        <div style="margin-bottom: 16px">
          <FormLabel>Last Name</FormLabel>
          <div class="input-group name-group">
            <span class="icon"><i class="fas fa-user"></i></span>
            <input
              type="text"
              v-model="last_name"
              placeholder="Last Name"
              ref="lastNameInput"
              required
            />
            <FormLabel v-if="errors.last_name" class="error-message1">{{
              errors.last_name[0]
            }}</FormLabel>
          </div>
        </div>

        <div style="margin-bottom: 16px">
          <FormLabel>Email ID</FormLabel>
          <div class="input-group email-group">
            <span class="icon"><i class="fas fa-envelope"></i></span>
            <input type="email" v-model="email" placeholder="Email ID" ref="emailInput" required />
            <FormLabel v-if="errors.email" class="error-message1">{{ errors.email[0] }}</FormLabel>
          </div>
        </div>

        <div style="margin-bottom: 16px">
          <FormLabel>Phone Number</FormLabel>
          <div class="input-group phone-group">
            <span class="icon"><i class="fas fa-phone"></i></span>
            <input
              type="tel"
              v-model="phone"
              placeholder="Phone Number"
              ref="phoneInput"
              required
            />
            <FormLabel v-if="errors.phone_number" class="error-message1">{{
              errors.phone_number[0]
            }}</FormLabel>
          </div>
        </div>

        <div style="margin-bottom: 16px">
          <FormLabel>Password</FormLabel>
          <div class="input-group password-group" :class="{ filled: password }">
            <span class="icon"><i class="fas fa-lock"></i></span>
            <input
              :type="showPassword ? 'text' : 'password'"
              v-model="password"
              placeholder="Password"
              ref="passwordInput"
              required
            />
            <button
              type="button"
              class="icon right"
              :aria-pressed="showPassword.toString()"
              @click.prevent="toggleShowPassword"
              title="Toggle password visibility"
            >
              <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
            </button>
            <FormLabel v-if="errors.password" class="error-message1">{{
              errors.password[0]
            }}</FormLabel>
          </div>
        </div>

        <div style="margin-bottom: 16px">
          <FormLabel>Confirm Password</FormLabel>
          <div class="input-group password-group" :class="{ filled: confirm_password }">
            <span class="icon"><i class="fas fa-lock"></i></span>
            <input
              :type="showConfirmPassword ? 'text' : 'password'"
              v-model="confirm_password"
              placeholder="Confirm Password"
              ref="confirmPasswordInput"
              required
            />
            <button
              type="button"
              class="icon right"
              :aria-pressed="showConfirmPassword.toString()"
              @click.prevent="toggleShowConfirmPassword"
              title="Toggle password visibility"
            >
              <i :class="showConfirmPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
            </button>
            <FormLabel v-if="errors.confirm_password" class="error-message1">{{
              errors.confirm_password[0]
            }}</FormLabel>
          </div>
        </div>

        <button type="submit" class="login-btn">Register</button>
      </form>

      <div class="switch-auth">
        <span>Already have an account?</span>
        <router-link to="/login" class="switch-link">Login here</router-link>
      </div>
    </div>
  </div>
</template>

<script>
import { FormLabel } from '@/components/Common/Common_UI/Form';
import axios from 'axios';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const API_BASE_URL = process.env.VUE_APP_API_BASE_URL;

export default {
  name: 'SimpleRegister',
  components: { Toast, FormLabel },
  setup() {
    const toast = useToast();
    return { toast };
  },
  data() {
    return {
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      phone_number: '',
      password: '',
      confirm_password: '',
      showPassword: false,
      showConfirmPassword: false,
      errors: {},
    };
  },
  methods: {
    async handleRegister() {
      this.errors = {};
      try {
        const payload = {
          first_name: this.first_name,
          last_name: this.last_name,
          email: this.email,
          phone_number: this.phone_number || this.phone,
          password: this.password,
          confirm_password: this.confirm_password,
        };

        const res = await axios.post(`${API_BASE_URL}/api/register-user`, payload);
        if (res.status === 201) {
          this.toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Registered successfully',
            life: 4000,
          });
          this.$router.push({
            name: 'Login',
            query: { email: this.email, registrationSuccess: true },
          });
        }
      } catch (error) {
        const data = error?.response?.data;
        if (data && data.errors) {
          this.errors = data.errors;
        } else if (data && data.message) {
          this.toast.add({
            severity: 'error',
            summary: 'Registration Error',
            detail: data.message,
            life: 6000,
          });
        } else {
          this.toast.add({
            severity: 'error',
            summary: 'Registration Error',
            detail: 'Registration failed. Please try again.',
            life: 6000,
          });
        }
      }
    },
    toggleShowPassword() {
      this.showPassword = !this.showPassword;
      // keep focus on input after toggle
      this.$nextTick(() => {
        if (this.$refs.passwordInput) this.$refs.passwordInput.focus();
      });
    },
    toggleShowConfirmPassword() {
      this.showConfirmPassword = !this.showConfirmPassword;
      this.$nextTick(() => {
        if (this.$refs.confirmPasswordInput) this.$refs.confirmPasswordInput.focus();
      });
    },
  },
  mounted() {
    this.$nextTick(() => {
      setTimeout(() => {
        if (this.$refs.firstNameInput) this.$refs.firstNameInput.focus();
      }, 50);
    });
  },
};
</script>

<style scoped>
/* Reuse existing minimal styles from Register.vue */
.input-group {
  position: relative;
  margin-bottom: 16px;
}
.input-group input {
  width: 100%;
  padding: 12px 12px 12px 48px;
  border: 1.5px solid #e0e0e0;
  border-radius: 12px;
  font-size: 1rem;
}
.input-group .icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: #787878;
  pointer-events: none;
}
.input-group .icon.right {
  pointer-events: auto;
  right: 12px;
  left: auto;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  cursor: pointer;
}
.input-group .icon.right:focus {
  outline: 2px solid rgba(0, 116, 194, 0.18);
}

.input-group.password-group.filled input,
.input-group.password-group input:focus {
  background: #e8f6ff; /* light blue */
  border-color: #87c6f5;
}
.login-card {
  background: #fff;
  padding: 32px;
  border-radius: 12px;
  max-width: 480px;
  margin: 0 auto;
}
.login-title {
  font-size: 1.6rem;
  margin-bottom: 4px;
}
.login-subtitle {
  color: #787878;
  margin-bottom: 16px;
}
.login-btn {
  width: 100%;
  padding: 12px;
  background: #0074c2;
  color: #fff;
  border-radius: 8px;
  border: none;
  cursor: pointer;
}
.error-message1 {
  color: red;
  font-size: 0.85rem;
  margin-top: 6px;
  display: block;
}
.switch-auth {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-top: 12px;
}
</style>
