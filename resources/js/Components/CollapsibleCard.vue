<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  id: { type: String, required: true },
  title: { type: String, required: true },
  headerClass: { type: String, default: 'mb-3' },
})

const STORAGE_KEY = 'sv_collapsed_cards'

function readCollapsed() {
  try {
    const list = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
    return Array.isArray(list) ? list : []
  } catch (e) {
    return []
  }
}

// Geräteweit statt pro Umfrage: wer beim Präsentieren einklappt, will das überall
const collapsed = ref(readCollapsed().includes(props.id))
const contentId = computed(() => `card-${props.id}`)

function toggle() {
  collapsed.value = !collapsed.value
  const others = readCollapsed().filter((id) => id !== props.id)
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(collapsed.value ? [...others, props.id] : others))
  } catch (e) {
    // nur Komfort
  }
}
</script>

<template>
  <section class="bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-2xl p-6">
    <div class="flex items-center justify-between gap-2" :class="collapsed ? '' : headerClass">
      <button
        type="button"
        @click="toggle"
        :aria-expanded="!collapsed"
        :aria-controls="contentId"
        class="group flex items-center gap-1.5 -my-1 py-1 text-xs font-medium uppercase tracking-wide text-[var(--color-sv-gray)] hover:text-[var(--color-sv-dark)] transition-colors"
      >
        <h2>{{ title }}</h2>
        <svg
          viewBox="0 0 16 16"
          class="block w-3.5 h-3.5 transition-transform"
          :class="collapsed ? '-rotate-90' : ''"
          fill="none"
          stroke="currentColor"
          stroke-width="1.75"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <path d="M4 6l4 4 4-4" />
        </svg>
      </button>
      <slot name="actions" />
    </div>
    <div v-show="!collapsed" :id="contentId">
      <slot />
    </div>
  </section>
</template>
