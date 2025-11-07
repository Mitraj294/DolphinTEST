<template>
  <component :is="elementTag" v-bind="forAttrs" class="form-label">
    <slot />
  </component>
</template>

<script>
export default {
  name: "FormLabel",
  props: {
    forId: { type: String, default: null },
    // When true, the label will be rendered even if `forId` is not provided
    // to support cases where the consumer wraps a control inside the slot.
    wrapsControl: { type: Boolean, default: false },
  },
  computed: {
    elementTag() {
      return this.forId || this.wrapsControl ? "label" : "div";
    },
    forAttrs() {
      if (this.elementTag === "label" && this.forId) {
        return { for: this.forId };
      }
      return {};
    },
  },
};
</script>

<style scoped>
.form-label {
  color: #222;
  font-size: 15px;
  font-weight: 400;
  text-align: left;
  margin-bottom: 6px;
  display: block;
}
</style>
