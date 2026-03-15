<template>
  <SkyConditionsWidget
    :lat="lat"
    :lon="lon"
    :tz="tz"
    :location-name="locationName"
    :initial-payload="initialPayload"
    :bundle-pending="bundlePending"
  />
</template>

<script setup>
import SkyConditionsWidget from '@/components/sky/SkyConditionsWidget.vue'

defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  date: { type: String, default: '' },
  tz: { type: String, default: 'Europe/Bratislava' },
  locationName: { type: String, default: '' },
  initialPayload: { type: Object, default: undefined },
  bundlePending: { type: Boolean, default: false },
})
</script>
