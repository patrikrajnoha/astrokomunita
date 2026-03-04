<template>
  <div class="space-y-4" aria-hidden="true">
    <section
      v-for="sectionIndex in sections"
      :key="`s-${sectionIndex}`"
      class="searchSection"
    >
      <div class="mb-3 h-3 w-28 animate-pulse rounded bg-[color:rgb(var(--color-text-secondary-rgb)/0.24)]"></div>

      <div class="space-y-2">
        <div
          v-for="rowIndex in rows"
          :key="`row-${sectionIndex}-${rowIndex}`"
          class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.18)] bg-[color:rgb(var(--color-bg-rgb)/0.56)] p-4 shadow-sm"
        >
          <div class="flex items-start gap-3">
            <div class="h-9 w-9 animate-pulse rounded-lg bg-[color:rgb(var(--color-text-secondary-rgb)/0.22)]"></div>
            <div class="flex-1 space-y-2">
              <div class="h-3.5 w-3/5 animate-pulse rounded bg-[color:rgb(var(--color-text-secondary-rgb)/0.24)]"></div>
              <div class="h-3 w-full animate-pulse rounded bg-[color:rgb(var(--color-text-secondary-rgb)/0.2)]"></div>
              <div class="h-2.5 w-2/5 animate-pulse rounded bg-[color:rgb(var(--color-text-secondary-rgb)/0.18)]"></div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
defineProps({
  sections: {
    type: Number,
    default: 2,
  },
  rows: {
    type: Number,
    default: 3,
  },
})
</script>

<style scoped>
.searchSection + .searchSection {
  border-top: 1px solid var(--divider-color);
  margin-top: 1rem;
  padding-top: 1rem;
}
</style>
