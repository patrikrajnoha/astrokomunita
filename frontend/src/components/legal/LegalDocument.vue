<template>
  <article class="legal-page">
    <nav class="legal-nav" aria-label="Legal pages">
      <RouterLink
        v-for="item in legalLinks"
        :key="item.to"
        :to="item.to"
        class="legal-nav-btn"
        :class="{ 'is-active': activePath === item.to }"
      >
        {{ item.label }}
      </RouterLink>
    </nav>

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
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

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

const route = useRoute()
const activePath = computed(() => route.path)
const legalLinks = [
  { to: '/privacy', label: 'Privacy' },
  { to: '/terms', label: 'Terms' },
  { to: '/cookies', label: 'Cookies' },
]
</script>

<style scoped>
.legal-page {
  width: 100%;
  max-width: 920px;
  margin: 0 auto;
  display: grid;
  gap: clamp(0.75rem, 2vw, 1rem);
  padding: clamp(0.25rem, 1.4vw, 0.85rem);
}

.legal-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.legal-nav-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  padding: 0 1rem;
  border: none;
  border-radius: 999px;
  background: #222e3f;
  color: #abb8c9;
  text-decoration: none;
  font-size: 0.84rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  transition: background-color 160ms ease, color 160ms ease, transform 120ms ease;
  box-shadow: none;
}

.legal-nav-btn:hover {
  background: #1c2736;
  color: #ffffff;
}

.legal-nav-btn:focus-visible {
  outline: 2px solid #0f73ff;
  outline-offset: 2px;
}

.legal-nav-btn:active {
  transform: scale(0.98);
}

.legal-nav-btn.is-active {
  background: #0f73ff;
  color: #ffffff;
}

.legal-hero,
.legal-card {
  border: none;
  border-radius: 1.1rem;
  background: #1c2736;
  box-shadow: none;
}

.legal-hero {
  padding: clamp(1rem, 2.4vw, 1.45rem);
  background: linear-gradient(180deg, #1c2736, #151d28);
}

.legal-card {
  padding: clamp(0.95rem, 2.1vw, 1.2rem);
}

.legal-eyebrow {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #0f73ff;
}

.legal-title {
  margin: 0.35rem 0 0;
  font-size: clamp(1.75rem, 4vw, 2.4rem);
  line-height: 1.08;
  color: #ffffff;
}

.legal-intro,
.legal-copy {
  margin: 0.7rem 0 0;
  line-height: 1.62;
  color: #abb8c9;
  max-width: 72ch;
}

.legal-copy + .legal-copy {
  margin-top: 0.82rem;
}

.legal-heading {
  margin: 0;
  font-size: 1.03rem;
  font-weight: 700;
  color: #ffffff;
}

@media (max-width: 640px) {
  .legal-nav {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .legal-nav-btn {
    width: 100%;
    padding: 0 0.55rem;
    font-size: 0.78rem;
  }

  .legal-hero,
  .legal-card {
    border-radius: 1rem;
  }

  .legal-intro,
  .legal-copy {
    line-height: 1.58;
  }
}

@media (max-width: 430px) {
  .legal-nav {
    grid-template-columns: 1fr;
  }
}
</style>
