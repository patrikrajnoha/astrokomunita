<template>
  <ObservingConditionsWidget
    :lat="lat"
    :lon="lon"
    :tz="tz"
    :location-name="locationName"
  />
</template>

<script setup>
import ObservingConditionsWidget from '@/components/sky/ObservingConditionsWidget.vue'

defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  tz: { type: String, default: '' },
  locationName: { type: String, default: '' },
})
</script>
