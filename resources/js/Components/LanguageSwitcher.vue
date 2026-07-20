<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { setLocale, SUPPORTED_LOCALES } from '@/i18n'

const { locale } = useI18n()
const open = ref(false)
const root = ref(null)

const LANGUAGES = {
  de: { flag: '🇩🇪', name: 'Deutsch' },
  en: { flag: '🇬🇧', name: 'English' },
  fr: { flag: '🇫🇷', name: 'Français' },
  es: { flag: '🇪🇸', name: 'Español' },
  nl: { flag: '🇳🇱', name: 'Nederlands' },
}

function select(code) {
  setLocale(code)
  open.value = false
}

function handleClickOutside(event) {
  if (root.value && !root.value.contains(event.target)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      @click="open = !open"
      class="flex items-center gap-1.5 text-sm px-2.5 py-1.5 rounded-lg border border-[var(--color-sv-gray-light)] hover:border-[var(--color-sv-accent)] cursor-pointer transition-colors"
    >
      <span class="text-base leading-none">{{ LANGUAGES[locale]?.flag }}</span>
      <svg
        viewBox="0 0 24 24"
        class="w-3.5 h-3.5 transition-transform"
        :style="{ transform: open ? 'rotate(180deg)' : 'rotate(0deg)' }"
      >
        <path d="M6 9l6 6 6-6" fill="none" stroke="var(--color-sv-gray)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <div
      v-if="open"
      class="absolute right-0 mt-2 w-44 bg-[var(--color-sv-surface)] border border-[var(--color-sv-gray-light)] rounded-xl shadow-lg py-1.5 z-50"
    >
      <button
        v-for="code in SUPPORTED_LOCALES"
        :key="code"
        type="button"
        @click="select(code)"
        class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-left cursor-pointer transition-colors"
        :class="code === locale
          ? 'bg-[var(--color-sv-accent-light)] text-[var(--color-sv-accent)] font-medium'
          : 'hover:bg-[var(--color-sv-bg)]'"
      >
        <span class="text-base leading-none">{{ LANGUAGES[code].flag }}</span>
        <span class="flex-1">{{ LANGUAGES[code].name }}</span>
        <span v-if="code === locale">✓</span>
      </button>
    </div>
  </div>
</template>
