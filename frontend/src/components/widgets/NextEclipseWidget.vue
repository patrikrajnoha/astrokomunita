<template>
  <NextEventWidget
    title="Zatmenie na obzore"
    endpoint="/events/widget/next-eclipse"
    empty-title="Žiadne blízke zatmenie"
    empty-text="V event databáze zatiaľ nie je žiadne publikované zatmenie."
    browse-label="Všetky udalosti"
    browse-to="/events?types=eclipse,eclipse_lunar,eclipse_solar"
  />
</template>

<script setup>
import NextEventWidget from './NextEventWidget.vue'
</script>
