<template>
  <LegalDocument
    :eyebrow="content.eyebrow"
    :title="content.title"
    :intro="content.intro"
    :sections="content.sections"
  />
</template>

<script setup>
import LegalDocument from '@/components/legal/LegalDocument.vue'
import { getLegalPageContent } from '@/legal/content'

const content = getLegalPageContent('privacy')
</script>
