<template>
  <article class="legal-page">
    <header class="legal-hero">
      <p class="legal-eyebrow">{{ eyebrow }}</p>
      <h1 class="legal-title">{{ title }}</h1>
      <p class="legal-intro">{{ intro }}</p>
    </header>

    <section
      v-for="section in sections"
      :key="section.heading"
      class="legal-card"
    >
      <h2 class="legal-heading">{{ section.heading }}</h2>
      <p
        v-for="paragraph in section.paragraphs"
        :key="paragraph"
        class="legal-copy"
      >
        {{ paragraph }}
      </p>
    </section>
  </article>
</template>

<script setup>
defineProps({
  eyebrow: {
    type: String,
    default: 'Legal',
  },
  title: {
    type: String,
    required: true,
  },
  intro: {
    type: String,
    required: true,
  },
  sections: {
    type: Array,
    default: () => [],
  },
})
</script>

<style scoped>
.legal-page {
  width: 100%;
  max-width: 860px;
  margin: 0 auto;
  display: grid;
  gap: 1rem;
}

.legal-hero,
.legal-card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  border-radius: 1.15rem;
  background: linear-gradient(160deg, rgb(var(--color-bg-rgb) / 0.9), rgb(var(--color-bg-rgb) / 0.65));
  box-shadow: 0 16px 44px rgb(0 0 0 / 0.2);
}

.legal-hero {
  padding: 1.35rem 1.25rem;
}

.legal-card {
  padding: 1.15rem 1.2rem;
}

.legal-eyebrow {
  margin: 0;
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.legal-title {
  margin: 0.35rem 0 0;
  font-size: clamp(1.9rem, 4vw, 2.4rem);
  line-height: 1.06;
  color: var(--color-surface);
}

.legal-intro,
.legal-copy {
  margin: 0.75rem 0 0;
  line-height: 1.7;
  color: rgb(var(--color-text-secondary-rgb) / 0.96);
}

.legal-heading {
  margin: 0;
  font-size: 1.02rem;
  font-weight: 700;
  color: var(--color-surface);
}

@media (max-width: 640px) {
  .legal-hero,
  .legal-card {
    padding: 1rem;
    border-radius: 1rem;
  }
}
</style>
