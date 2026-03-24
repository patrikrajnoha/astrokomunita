<template>
  <NextEventWidget
    title="Padajúce hviezdy"
    endpoint="/events/widget/next-meteor-shower"
    empty-title="Žiadny blízky meteorický roj"
    empty-text="V event databáze zatiaľ nie je žiadny publikovaný meteorický roj."
    browse-label="Všetky meteory"
    browse-to="/events?types=meteors,meteor_shower"
  />
</template>

<script setup>
import NextEventWidget from './NextEventWidget.vue'
</script>
