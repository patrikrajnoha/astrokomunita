<template>
  <video class="postMediaVideo" controls preload="metadata">
    <source :src="src" :type="type || undefined">
  </video>
</template>

<script setup>
defineProps({
  src: {
    type: String,
    required: true,
  },
  type: {
    type: String,
    default: '',
  },
})
</script>

<style scoped>
.postMediaVideo {
  width: 100%;
  border-radius: 12px;
  border: 1px solid rgb(var(--color-text-secondary-rgb, 148 163 184) / 0.2);
  background: rgb(var(--color-bg-rgb, 15 23 42) / 0.7);
}
</style>
