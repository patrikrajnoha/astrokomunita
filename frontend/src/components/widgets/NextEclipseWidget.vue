<template>
  <NextEventWidget
    title="Najblizsie zatmenie"
    endpoint="/events/widget/next-eclipse"
    empty-title="Ziadne blizke zatmenie"
    empty-text="V event databaze zatial nie je ziadne publikovane zatmenie."
    browse-label="Vsetky udalosti"
    browse-to="/events?types=eclipse,eclipse_lunar,eclipse_solar"
  />
</template>

<script setup>
import NextEventWidget from './NextEventWidget.vue'
</script>
