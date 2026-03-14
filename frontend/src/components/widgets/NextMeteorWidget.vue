<template>
  <NextEventWidget
    title="Najblizsi meteoricky roj"
    endpoint="/events/widget/next-meteor-shower"
    empty-title="Ziadny blizky meteoricky roj"
    empty-text="V event databaze zatial nie je ziadny publikovany meteoricky roj."
    browse-label="Vsetky meteory"
    browse-to="/events?types=meteors,meteor_shower"
  />
</template>

<script setup>
import NextEventWidget from './NextEventWidget.vue'
</script>
