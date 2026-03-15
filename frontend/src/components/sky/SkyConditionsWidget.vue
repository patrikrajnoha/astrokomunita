<template>
  <ObservingConditionsWidget
    :lat="lat"
    :lon="lon"
    :tz="tz"
    :location-name="locationName"
    :initial-payload="initialPayload"
    :bundle-pending="bundlePending"
  />
</template>

<script setup>
import ObservingConditionsWidget from '@/components/sky/ObservingConditionsWidget.vue'

defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  tz: { type: String, default: '' },
  locationName: { type: String, default: '' },
  initialPayload: { type: Object, default: undefined },
  bundlePending: { type: Boolean, default: false },
})
</script>
