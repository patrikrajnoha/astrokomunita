<template>
  <div class="searchSkeleton" aria-hidden="true">
    <section
      v-for="sectionIndex in sections"
      :key="`s-${sectionIndex}`"
      class="searchSkeleton__section"
    >
      <div class="searchSkeleton__heading ui-skeleton"></div>

      <div class="searchSkeleton__list">
        <div
          v-for="rowIndex in rows"
          :key="`row-${sectionIndex}-${rowIndex}`"
          class="searchSkeleton__item"
        >
          <div class="searchSkeleton__row">
            <div class="searchSkeleton__icon ui-skeleton"></div>
            <div class="searchSkeleton__content">
              <div class="searchSkeleton__line searchSkeleton__line--title ui-skeleton"></div>
              <div class="searchSkeleton__line searchSkeleton__line--body ui-skeleton"></div>
              <div class="searchSkeleton__line searchSkeleton__line--meta ui-skeleton"></div>
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
.searchSkeleton {
  display: grid;
  gap: 0.75rem;
}

.searchSkeleton__section {
  display: grid;
  gap: 0.58rem;
}

.searchSkeleton__heading {
  width: 7rem;
  height: 0.6rem;
  border-radius: 999px;
}

.searchSkeleton__list {
  display: grid;
  gap: 0.45rem;
}

.searchSkeleton__item {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--bg-app-rgb) / 0.45);
  padding: 0.72rem;
}

.searchSkeleton__row {
  display: flex;
  align-items: flex-start;
  gap: 0.62rem;
}

.searchSkeleton__icon {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-sm);
}

.searchSkeleton__content {
  flex: 1;
  display: grid;
  gap: 0.34rem;
}

.searchSkeleton__line {
  height: 0.56rem;
}

.searchSkeleton__line--title {
  width: 62%;
}

.searchSkeleton__line--body {
  width: 100%;
}

.searchSkeleton__line--meta {
  width: 48%;
}
</style>
