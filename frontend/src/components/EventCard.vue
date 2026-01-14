<script setup>
import { ref } from 'vue'
import { addFavorite, removeFavorite } from '@/services/favorites'

const props = defineProps({
  event: {
    type: Object,
    required: true
  }
})

const isFavorite = ref(false)

const toggleFavorite = async () => {
  if (isFavorite.value) {
    await removeFavorite(props.event.id)
  } else {
    await addFavorite(props.event.id)
  }
  isFavorite.value = !isFavorite.value
}
</script>


<template>
  <div class="event-card">
    <h3>{{ event.name }}</h3>
    <p>{{ event.description }}</p>
    <p><strong>Dátum:</strong> {{ event.date }}</p>

    <button @click="toggleFavorite">
      {{ isFavorite ? '★ Obľúbené' : '☆ Pridať medzi obľúbené' }}
    </button>
  </div>
</template>
